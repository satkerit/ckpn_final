<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Job antrian untuk memproses upload Excel data jaminan (agunan) secara async & memory-efficient.
 *
 * POSISI DALAM ALUR: DATA MASUKAN — upload master data jaminan sebelum proses LGD-CS.
 * Data jaminan diperlukan oleh LgdCollateralShortfallCalculator untuk menghitung
 * Collateral Net Value dan Shortfall per akun (Ref: PRD Bab 10).
 *
 * IMPLEMENTASI HEMAT MEMORI:
 *   1. Memakai PhpSpreadsheet ReadDataOnly (tanpa memuat style / seluruh collection ke RAM).
 *   2. Preload lookup dictionary (account_number -> id & collateral_type_code -> id).
 *   3. Bulk upsert per batch (BATCH_SIZE = 1000) ke tabel collaterals.
 *   4. Garbage collection & disconnection worksheet saat selesai.
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
    use InteractsWithQueue, Queueable, SerializesModels;

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

            // 2. Baca XLSX dengan ReadDataOnly = true (memory-friendly streaming)
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($this->filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $importedRows = 0;
            $skippedRows = 0;
            $skippedNotFound = 0;
            $skippedInvalidType = 0;
            $processedRows = 0;
            $errors = [];
            $buffer = [];

            // 3. Mapping Heading Row (Baris 1)
            $headings = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $headings[] = strtolower(trim((string) $cell->getValue()));
                }
            }
            $headingMap = array_flip($headings);

            // Kolom aliases
            $colAccount = $this->resolveColIndex($headingMap, ['account_number', 'nokontrak', 'no_kontrak', 'nomor_kontrak']);
            $colCode = $this->resolveColIndex($headingMap, ['collateral_code', 'kode_agunan', 'kode_jaminan', 'collateral_id', 'id_agunan']);
            $colSeq = $this->resolveColIndex($headingMap, ['sequence_number', 'no_urut', 'seq', 'urut']);
            $colType = $this->resolveColIndex($headingMap, ['collateral_type_code', 'jenis_jaminan', 'jenis_agunan', 'kode_jenis']);
            $colDesc = $this->resolveColIndex($headingMap, ['description', 'keterangan', 'deskripsi', 'nama_jaminan']);
            $colAppraisal = $this->resolveColIndex($headingMap, ['appraisal_value', 'nilai_taksasi', 'nilai_pasar', 'nilai_agunan']);
            $colLiquidation = $this->resolveColIndex($headingMap, ['estimated_sale_value', 'nilai_likuidasi', 'njop']);
            $colAppraisedAt = $this->resolveColIndex($headingMap, ['appraised_at', 'tanggal_taksasi', 'tgl_penilaian', 'tgl_taksasi']);
            $colActive = $this->resolveColIndex($headingMap, ['is_active', 'status_aktif', 'aktif']);

            // 4. Iterasi Baris Data Mulai Baris 2
            $highestRow = $sheet->getHighestDataRow();

            for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                $processedRows++;
                $rowData = [];

                foreach ($sheet->getRowIterator($rowNum, $rowNum) as $row) {
                    $i = 0;
                    foreach ($row->getCellIterator() as $cell) {
                        $rowData[$i] = $cell->getValue();
                        $i++;
                    }
                }

                $accountNumber = trim((string) ($rowData[$colAccount] ?? ''));
                $collateralCode = trim((string) ($rowData[$colCode] ?? ''));

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
                $typeCode = trim((string) ($rowData[$colType] ?? ''));
                $typeId = $typeCache[$typeCode] ?? null;
                if ($typeId === null) {
                    $skippedInvalidType++;
                    $skippedRows++;

                    continue;
                }

                $seqVal = $rowData[$colSeq] ?? null;
                $sequenceNumber = ($seqVal !== null && $seqVal !== '') ? (int) $seqVal : 1;

                $rawActive = $rowData[$colActive] ?? null;
                $isActive = ($rawActive !== null && (string) $rawActive !== '')
                    ? (bool) (int) $rawActive
                    : true;

                $buffer[] = [
                    'financing_account_id' => $accountId,
                    'collateral_code' => $collateralCode,
                    'sequence_number' => $sequenceNumber,
                    'collateral_type_id' => $typeId,
                    'description' => isset($rowData[$colDesc]) ? (string) $rowData[$colDesc] : null,
                    'appraisal_value' => $this->parseDecimal($rowData[$colAppraisal] ?? null),
                    'estimated_sale_value' => $this->parseDecimal($rowData[$colLiquidation] ?? null),
                    'appraised_at' => $this->parseDate($rowData[$colAppraisedAt] ?? null),
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

            // Bersihkan memory spreadsheet
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            gc_collect_cycles();

            // Ringkas error_summary
            $errorSummary = $errors ?: [];
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
                'failed_rows' => count($errors) + $skippedNotFound + $skippedInvalidType,
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
     *
     * @param  array<int, array<string, mixed>>  $buffer
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

    /**
     * Resolusi indeks kolom berdasarkan alias.
     *
     * @param  array<string, int>  $headingMap
     * @param  array<int, string>  $aliases
     */
    private function resolveColIndex(array $headingMap, array $aliases): int
    {
        foreach ($aliases as $alias) {
            if (isset($headingMap[$alias])) {
                return $headingMap[$alias];
            }
        }

        return -1;
    }

    private function parseDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = str_replace([',', ' '], ['', ''], (string) $value);

        return is_numeric($cleaned) ? $cleaned : null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);

        // Format yyyymmdd
        if (preg_match('/^\d{8}$/', $str)) {
            return Carbon::createFromFormat('Ymd', $str)?->toDateString();
        }

        // Format Excel serial number
        if (is_numeric($str) && strlen($str) <= 5) {
            return Carbon::createFromTimestamp(((int) $str - 25569) * 86400)?->toDateString();
        }

        try {
            return Carbon::parse($str)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
