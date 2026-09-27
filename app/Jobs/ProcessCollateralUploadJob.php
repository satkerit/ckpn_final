<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\DB;

/**
 * Job antrian untuk memproses upload Excel data jaminan (agunan) secara async & memory-efficient.
 *
 * POSISI DALAM ALUR: DATA MASUKAN — upload master data jaminan sebelum proses LGD-CS.
 * Data jaminan diperlukan oleh LgdCollateralShortfallCalculator untuk menghitung
 * Collateral Net Value dan Shortfall per akun (Ref: PRD Bab 10).
 *
 * IMPLEMENTASI OPENSPOUT:
 *   - Streaming read tanpa load seluruh file ke memori
 *   - Memory konstan ~30-50MB berapapun ukuran file
 *   - Bulk upsert per batch ke tabel collaterals
 *
 * Ref: PRD Bab 10 (LGD-CS), Bab 15 (tabel collaterals)
 */
class ProcessCollateralUploadJob extends UploadJobBase
{
    public int $timeout = 600;

    private const int BATCH_SIZE = 1000;

    /**
     * Implementasi parsing data jaminan.
     */
    protected function process(FinancingUploadBatch $batch): void
    {
        // 1. Preload lookup caches (1 query masing-masing untuk efisiensi O(1))
        $accountCache = DB::table('financing_accounts')
            ->pluck('id', 'account_number')
            ->map(fn ($id) => (int) $id)
            ->all();

        $typeCache = DB::table('collateral_types')
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();

        // 2. Baca XLSX dengan OpenSpout streaming
        $reader = $this->createReader($this->filePath);
        $headingMap = $this->extractHeadings($reader);

        // Reopen reader untuk iterasi data
        $this->closeReader($reader);
        $reader = $this->createReader($this->filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $skippedNotFound = 0;
        $skippedInvalidType = 0;
        $processedRows = 0;
        $buffer = [];

        // Resolve column indexes
        $colAccount = $this->resolveColIndex($headingMap, ['account_number', 'nokontrak', 'no_kontrak', 'nomor_kontrak']);
        $colCode = $this->resolveColIndex($headingMap, ['collateral_code', 'kode_agunan', 'kode_jaminan', 'collateral_id', 'id_agunan']);
        $colSeq = $this->resolveColIndex($headingMap, ['sequence_number', 'no_urut', 'seq', 'urut']);
        $colType = $this->resolveColIndex($headingMap, ['collateral_type_code', 'jenis_jaminan', 'jenis_agunan', 'kode_jenis']);
        $colDesc = $this->resolveColIndex($headingMap, ['description', 'keterangan', 'deskripsi', 'nama_jaminan']);
        $colAppraisal = $this->resolveColIndex($headingMap, ['appraisal_value', 'nilai_taksasi', 'nilai_pasar', 'nilai_agunan']);
        $colLiquidation = $this->resolveColIndex($headingMap, ['estimated_sale_value', 'nilai_likuidasi', 'njop']);
        $colAppraisedAt = $this->resolveColIndex($headingMap, ['appraised_at', 'tanggal_taksasi', 'tgl_penilaian', 'tgl_taksasi']);
        $colActive = $this->resolveColIndex($headingMap, ['is_active', 'status_aktif', 'aktif']);

        // 3. Stream rows
        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            $accountNumber = $this->parseString($this->getCellValue($rowData, $colAccount));
            $collateralCode = $this->parseString($this->getCellValue($rowData, $colCode));

            if ($accountNumber === '' && $collateralCode === '') {
                $skippedRows++;

                continue;
            }

            if ($accountNumber === '') {
                $skippedRows++;

                continue;
            }

            if ($collateralCode === '') {
                $collateralCode = 'JMN-'.$accountNumber;
            }

            $accountId = $accountCache[$accountNumber] ?? null;
            if ($accountId === null) {
                $skippedNotFound++;
                $skippedRows++;

                continue;
            }

            $typeCode = $this->parseString($this->getCellValue($rowData, $colType));
            $typeId = $typeCache[$typeCode] ?? null;
            if ($typeId === null) {
                $skippedInvalidType++;
                $skippedRows++;

                continue;
            }

            $seqVal = $this->getCellValue($rowData, $colSeq);
            $sequenceNumber = ($seqVal !== null && $seqVal !== '') ? (int) $seqVal : 1;

            $rawActive = $this->getCellValue($rowData, $colActive);
            $isActive = $this->parseBoolean($rawActive, true);

            $buffer[] = [
                'financing_account_id' => $accountId,
                'collateral_code' => $collateralCode,
                'sequence_number' => $sequenceNumber,
                'collateral_type_id' => $typeId,
                'description' => $this->parseString($this->getCellValue($rowData, $colDesc)) ?: null,
                'appraisal_value' => $this->parseDecimal($this->getCellValue($rowData, $colAppraisal)),
                'estimated_sale_value' => $this->parseDecimal($this->getCellValue($rowData, $colLiquidation)),
                'appraised_at' => $this->parseDate($this->getCellValue($rowData, $colAppraisedAt)),
                'is_active' => $isActive,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];

            if (count($buffer) >= self::BATCH_SIZE) {
                $this->flushBuffer($buffer, $importedRows);
                $batch->update([
                    'processed_rows' => $processedRows,
                    'imported_rows' => $importedRows,
                    'skipped_rows' => $skippedRows,
                ]);
            }
        }

        // Flush sisa buffer
        if (! empty($buffer)) {
            $this->flushBuffer($buffer, $importedRows);
        }

        $this->closeReader($reader);

        $errorSummary = [];
        if ($skippedNotFound > 0) {
            $errorSummary[] = "account_number tidak ditemukan di financing_accounts: {$skippedNotFound} baris di-skip.";
        }
        if ($skippedInvalidType > 0) {
            $errorSummary[] = "collateral_type_code tidak ditemukan di collateral_types: {$skippedInvalidType} baris di-skip.";
        }

        $batch->update([
            'status' => UploadBatchStatus::Done,
            'total_rows' => $processedRows,
            'imported_rows' => $importedRows,
            'failed_rows' => $skippedNotFound + $skippedInvalidType,
            'skipped_rows' => $skippedRows,
            'processed_rows' => $processedRows,
            'error_summary' => $errorSummary ?: null,
            'progress_log' => [
                'finished_at' => now()->toDateTimeString(),
                'total' => $processedRows,
                'imported' => $importedRows,
                'skipped' => $skippedRows,
                'skipped_not_found' => $skippedNotFound,
                'skipped_invalid_type' => $skippedInvalidType,
            ],
        ]);
    }

    /**
     * Bulk upsert buffer ke tabel collaterals.
     */
    private function flushBuffer(array &$buffer, int &$importedRows): void
    {
        if (empty($buffer)) {
            return;
        }

        DB::table('collaterals')->upsert(
            $buffer,
            ['financing_account_id', 'collateral_code', 'sequence_number'],
            [
                'collateral_type_id',
                'description',
                'appraisal_value',
                'estimated_sale_value',
                'appraised_at',
                'is_active',
                'updated_at',
            ]
        );

        $importedRows += count($buffer);
        $buffer = [];
    }
}
