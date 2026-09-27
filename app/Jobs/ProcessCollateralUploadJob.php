<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use App\Traits\StreamableExcelUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

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
 * FORMAT FILE EXCEL (.xlsx):
 *   Baris 1 = header kolom.
 *   Kolom didukung (case-insensitive):
 *     - account_number / nokontrak
 *     - collateral_code / kode_agunan / kode_jaminan
 *     - sequence_number / no_urut / seq
 *     - collateral_type_code / jenis_jaminan / jenis_agunan
 *     - description / keterangan / deskripsi
 *     - appraisal_value / nilai_taksasi / nilai_pasar
 *     - estimated_sale_value / nilai_likuidasi / njop
 *     - appraised_at / tanggal_taksasi / tgl_penilaian
 *     - is_active / status_aktif
 *
 * Ref: PRD Bab 10 (LGD-CS), Bab 15 (tabel collaterals)
 */
class ProcessCollateralUploadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, StreamableExcelUpload;

    public int $tries = 3;

    public int $timeout = 600;

    private const int BATCH_SIZE = 1000;

    public function __construct(
        private readonly int $batchId,
        private readonly string $filePath,
    ) {}

    /**
     * Eksekusi utama job upload data jaminan secara streaming chunk & bulk upsert.
     */
    public function handle(): void
    {
        $batch = FinancingUploadBatch::find($this->batchId);
        if ($batch === null) {
            return;
        }

        // Idempotency guard
        if ($batch->status === UploadBatchStatus::Done) {
            return;
        }

        if (! file_exists($this->filePath)) {
            $batch->update([
                'status' => UploadBatchStatus::Failed,
                'error_summary' => ["File tidak ditemukan: {$this->filePath}"],
            ]);

            return;
        }

        $batch->update(['status' => UploadBatchStatus::Processing]);

        try {
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

                // Jika baris kosong total
                if ($accountNumber === '' && $collateralCode === '') {
                    $skippedRows++;

                    continue;
                }

                if ($accountNumber === '') {
                    $skippedRows++;

                    continue;
                }

                // Default collateral_code jika kosong
                if ($collateralCode === '') {
                    $collateralCode = 'JMN-'.$accountNumber;
                }

                // Resolve account ID
                $accountId = $accountCache[$accountNumber] ?? null;
                if ($accountId === null) {
                    $skippedNotFound++;
                    $skippedRows++;

                    continue;
                }

                // Resolve collateral type ID
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
                $isActive = ($rawActive !== null && (string) $rawActive !== '')
                    ? (bool) (int) $rawActive
                    : true;

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

            // Ringkas error_summary
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
        } catch (Throwable $e) {
            $batch->update([
                'status' => UploadBatchStatus::Failed,
                'error_summary' => [$e->getMessage()],
            ]);
            throw $e;
        }
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
