<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Queued job untuk memproses upload Excel master data kantor pembiayaan secara async.
 *
 * POSISI DALAM ALUR: DATA MASTER — referensi hierarki kantor untuk pengelompokan akun.
 *
 * IMPLEMENTASI OPENSPOUT:
 *   - Streaming read tanpa load seluruh file ke memori
 *   - Memory konstan ~30-50MB berapapun ukuran file
 *   - Bulk upsert per batch ke tabel financing_offices
 *
 * Ref: PRD Bab 15 (tabel financing_offices)
 */
class ProcessFinancingOfficeUploadJob extends UploadJobBase
{
    public int $timeout = 300;

    private const int BATCH_SIZE = 500;

    /**
     * Implementasi parsing master data kantor.
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
        $colCode = $this->resolveColIndex($headingMap, ['code', 'kode_kantor', 'kode', 'office_code']);
        $colName = $this->resolveColIndex($headingMap, ['name', 'nama_kantor', 'nama']);
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

            $buffer[] = [
                'code' => $code,
                'name' => $this->parseString($this->getCellValue($rowData, $colName)) ?: $code,
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
     * Bulk upsert buffer ke tabel financing_offices.
     */
    private function flushBuffer(array &$buffer, int &$importedRows, array &$errors): void
    {
        if (empty($buffer)) {
            return;
        }

        try {
            DB::table('financing_offices')->upsert(
                $buffer,
                ['code'],
                ['name', 'is_active', 'updated_at']
            );

            $importedRows += count($buffer);
        } catch (Throwable $e) {
            $errors[] = "Batch insert failed: {$e->getMessage()}";
        }

        $buffer = [];
    }
}
