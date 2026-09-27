<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Job antrian untuk memproses upload Excel master data jenis jaminan (collateral types) secara async.
 *
 * POSISI DALAM ALUR: DATA MASTER — harus diupload SEBELUM data jaminan (collaterals).
 * Tabel collateral_types menyimpan referensi kode + nama jenis jaminan yang dipakai
 * sebagai FK di tabel collaterals.
 *
 * IMPLEMENTASI OPENSPOUT:
 *   - Streaming read tanpa load seluruh file ke memori
 *   - Memory konstan ~30-50MB berapapun ukuran file
 *   - Bulk upsert per batch ke tabel collateral_types
 *
 * FORMAT FILE EXCEL (.xlsx):
 *   Baris 1 = header kolom (case-insensitive):
 *     - code / kode_jaminan (wajib, PK)
 *     - name / nama_jaminan
 *     - liquidation_discount_rate / haircut_rate / diskon (decimal 0-1)
 *     - is_active / aktif (boolean)
 *
 * Ref: PRD Bab 10 (LGD-CS, haircut), Bab 15 (tabel collateral_types)
 */
class ProcessCollateralTypeUploadJob extends UploadJobBase
{
    public int $timeout = 300;

    private const int BATCH_SIZE = 500;

    /**
     * Implementasi parsing master jenis jaminan.
     */
    protected function process(FinancingUploadBatch $batch): void
    {
        // Baca XLSX dengan OpenSpout streaming
        $reader = $this->createReader($this->filePath);
        $headingMap = $this->extractHeadings($reader);

        // Reopen reader untuk iterasi data
        $this->closeReader($reader);
        $reader = $this->createReader($this->filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $processedRows = 0;
        $errors = [];
        $buffer = [];

        // Resolve column indexes
        $colCode = $this->resolveColIndex($headingMap, ['code', 'kode_jaminan', 'kode', 'collateral_type_code']);
        $colName = $this->resolveColIndex($headingMap, ['name', 'nama_jaminan', 'nama']);
        $colRate = $this->resolveColIndex($headingMap, ['liquidation_discount_rate', 'haircut_rate', 'diskon', 'rate']);
        $colActive = $this->resolveColIndex($headingMap, ['is_active', 'aktif', 'active']);

        // Stream rows
        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            $code = $this->parseString($this->getCellValue($rowData, $colCode));

            if ($code === '') {
                $skippedRows++;

                continue;
            }

            $rawActive = $this->getCellValue($rowData, $colActive);
            $isActive = $this->parseBoolean($rawActive, true);

            $rateRaw = $this->getCellValue($rowData, $colRate);
            $rate = $this->parseDecimal($rateRaw);

            // Validasi rate 0-1
            if ($rate !== null && ((float) $rate < 0 || (float) $rate > 1)) {
                $errors[] = "Row {$rowNum}: liquidation_discount_rate harus antara 0-1, nilai: {$rate}";
                $rate = null;
            }

            $buffer[] = [
                'code' => $code,
                'name' => $this->parseString($this->getCellValue($rowData, $colName)) ?: $code,
                'liquidation_discount_rate' => $rate,
                'is_active' => $isActive,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            if (count($buffer) >= self::BATCH_SIZE) {
                $this->flushBuffer($buffer, $importedRows, $errors);
                $batch->update([
                    'processed_rows' => $processedRows,
                    'imported_rows' => $importedRows,
                    'skipped_rows' => $skippedRows,
                ]);
            }
        }

        // Flush sisa buffer
        if (! empty($buffer)) {
            $this->flushBuffer($buffer, $importedRows, $errors);
        }

        $this->closeReader($reader);

        $batch->update([
            'status' => UploadBatchStatus::Done,
            'total_rows' => $processedRows,
            'imported_rows' => $importedRows,
            'failed_rows' => count($errors),
            'skipped_rows' => $skippedRows,
            'processed_rows' => $processedRows,
            'error_summary' => $errors ?: null,
            'progress_log' => [
                'finished_at' => now()->toDateTimeString(),
                'total' => $processedRows,
                'imported' => $importedRows,
                'skipped' => $skippedRows,
                'errors' => count($errors),
            ],
        ]);
    }

    /**
     * Bulk upsert buffer ke tabel collateral_types.
     */
    private function flushBuffer(array &$buffer, int &$importedRows, array &$errors): void
    {
        if (empty($buffer)) {
            return;
        }

        try {
            DB::table('collateral_types')->upsert(
                $buffer,
                ['code'],
                ['name', 'liquidation_discount_rate', 'is_active', 'updated_at']
            );

            $importedRows += count($buffer);
        } catch (Throwable $e) {
            $errors[] = "Batch insert failed: {$e->getMessage()}";
        }

        $buffer = [];
    }
}