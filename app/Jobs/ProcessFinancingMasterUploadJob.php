<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Enums\UsageType;
use App\Models\FinancingUploadBatch;
use App\Traits\HasProgressTracking;
use App\Traits\StreamableExcelUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Queued job untuk memproses upload Excel master data pembiayaan secara async.
 *
 * POSISI DALAM ALUR: DATA MASTER — harus diupload SEBELUM data historis periode.
 * Tabel financing_accounts adalah data induk yang menjadi referensi untuk seluruh
 * data historis per periode (financing_account_periods).
 *
 * IMPLEMENTASI OPENSPOUT:
 *   - Streaming read tanpa load seluruh file ke memori
 *   - Memory konstan ~30-50MB berapapun ukuran file
 *   - Bulk upsert per batch ke tabel financing_accounts
 *
 * FORMAT FILE EXCEL (.xlsx):
 *   Baris 1 = header kolom (case-insensitive):
 *     - account_number / no_kontrak (wajib, PK)
 *     - customer_name / nama_nasabah
 *     - product_code / kode_produk
 *     - akad_code / kode_akad
 *     - office_code / kode_kantor
 *     - economic_sector / sektor_ekonomi
 *     - usage_type / tujuan_penggunaan (1=ModalKerja, 2=Investasi, 3=Konsumsi)
 *
 * Ref: PRD Bab 15 (tabel financing_accounts)
 */
class ProcessFinancingMasterUploadJob implements ShouldQueue
{
    use HasProgressTracking, InteractsWithQueue, Queueable, SerializesModels, StreamableExcelUpload;

    public int $tries = 3;

    public int $timeout = 600;

    private const int BATCH_SIZE = 1000;

    public function __construct(
        private readonly int $batchId,
        private readonly string $filePath,
    ) {}

    /**
     * Eksekusi utama job upload master data pembiayaan.
     */
    public function handle(): void
    {
        $batch = FinancingUploadBatch::find($this->batchId);
        if ($batch === null) {
            return;
        }

        // Initialize progress tracking
        $this->initializeProgress((string) $this->batchId);

        // Idempotency guard
        if ($batch->status === UploadBatchStatus::Done) {
            return;
        }

        if (! file_exists($this->filePath)) {
            $batch->update([
                'status' => UploadBatchStatus::Failed,
                'error_summary' => ["File tidak ditemukan: {$this->filePath}"],
            ]);
            $this->failProgress("File tidak ditemukan: {$this->filePath}");

            return;
        }

        $batch->update(['status' => UploadBatchStatus::Processing]);

        $this->updateProgress([
            'status_title' => 'Membaca file...',
            'status_text' => 'Menganalisis struktur file Excel',
            'file_info' => basename($this->filePath).' ('.$this->formatFileSize(filesize($this->filePath)).')',
        ]);

        try {
            // Baca XLSX dengan OpenSpout streaming
            $reader = $this->createReader($this->filePath);
            $headingMap = $this->extractHeadings($reader);

            // Count total rows for progress
            $totalRows = $this->countTotalRows($reader);
            $this->totalSteps = $totalRows;

            $this->updateProgress([
                'total_steps' => $totalRows,
                'status_title' => 'Memproses data...',
                'status_text' => "Total {$totalRows} baris data ditemukan",
            ]);

            // Reopen reader untuk iterasi data
            $this->closeReader($reader);
            $reader = $this->createReader($this->filePath);

            $importedRows = 0;
            $skippedRows = 0;
            $processedRows = 0;
            $errors = [];
            $buffer = [];

            // Resolve column indexes
            $colAccount = $this->resolveColIndex($headingMap, ['account_number', 'no_kontrak', 'nomor_kontrak', 'nokontrak']);
            $colCustomer = $this->resolveColIndex($headingMap, ['customer_name', 'nama_nasabah', 'nama']);
            $colProduct = $this->resolveColIndex($headingMap, ['product_code', 'kode_produk', 'produk']);
            $colAkad = $this->resolveColIndex($headingMap, ['akad_code', 'kode_akad', 'akad']);
            $colOffice = $this->resolveColIndex($headingMap, ['office_code', 'kode_kantor', 'kantor']);
            $colSector = $this->resolveColIndex($headingMap, ['economic_sector', 'sektor_ekonomi', 'sektor']);
            $colUsage = $this->resolveColIndex($headingMap, ['usage_type', 'tujuan_penggunaan', 'tujuan']);

            // Stream rows
            foreach ($this->streamRows($reader) as $rowNum => $rowData) {
                $processedRows++;

                try {
                    $accountNumber = $this->parseString($this->getCellValue($rowData, $colAccount));

                    if ($accountNumber === '') {
                        $skippedRows++;
                        $errors[] = [
                            'row' => $rowNum + 2, // +2 karena header di row 1 dan array 0-based
                            'field' => 'account_number',
                            'value' => 'kosong',
                            'error' => 'Nomor kontrak tidak boleh kosong',
                        ];

                        continue;
                    }

                    $usageTypeRaw = $this->getCellValue($rowData, $colUsage);
                    $usageType = null;
                    if ($usageTypeRaw !== null && $usageTypeRaw !== '') {
                        $usageType = UsageType::tryFrom((int) $usageTypeRaw);
                        if ($usageType === null && $usageTypeRaw !== '') {
                            $errors[] = [
                                'row' => $rowNum + 2,
                                'field' => 'usage_type',
                                'value' => $usageTypeRaw,
                                'error' => 'Tipe penggunaan tidak valid. Gunakan 1=Modal Kerja, 2=Investasi, 3=Konsumsi',
                            ];
                        }
                    }

                    // Validasi panjang field
                    $customerName = $this->parseString($this->getCellValue($rowData, $colCustomer));
                    if (strlen($customerName) > 255) {
                        $errors[] = [
                            'row' => $rowNum + 2,
                            'field' => 'customer_name',
                            'value' => substr($customerName, 0, 50).'...',
                            'error' => 'Nama nasabah terlalu panjang (maksimal 255 karakter)',
                        ];
                        $customerName = substr($customerName, 0, 255);
                    }

                    $productCode = $this->parseString($this->getCellValue($rowData, $colProduct));
                    if (strlen($productCode) > 50) {
                        $errors[] = [
                            'row' => $rowNum + 2,
                            'field' => 'product_code',
                            'value' => $productCode,
                            'error' => 'Kode produk terlalu panjang (maksimal 50 karakter)',
                        ];
                        $productCode = substr($productCode, 0, 50);
                    }

                    $akadCode = $this->parseString($this->getCellValue($rowData, $colAkad));
                    if (strlen($akadCode) > 50) {
                        $errors[] = [
                            'row' => $rowNum + 2,
                            'field' => 'akad_code',
                            'value' => $akadCode,
                            'error' => 'Kode akad terlalu panjang (maksimal 50 karakter)',
                        ];
                        $akadCode = substr($akadCode, 0, 50);
                    }

                    $officeCode = $this->parseString($this->getCellValue($rowData, $colOffice));
                    if (strlen($officeCode) > 20) {
                        $errors[] = [
                            'row' => $rowNum + 2,
                            'field' => 'office_code',
                            'value' => $officeCode,
                            'error' => 'Kode kantor terlalu panjang (maksimal 20 karakter)',
                        ];
                        $officeCode = substr($officeCode, 0, 20);
                    }

                    $buffer[] = [
                        'account_number' => $accountNumber,
                        'customer_name' => $customerName ?: null,
                        'product_code' => $productCode ?: null,
                        'akad_code' => $akadCode ?: null,
                        'office_code' => $officeCode ?: null,
                        'economic_sector' => $this->parseString($this->getCellValue($rowData, $colSector)) ?: null,
                        'usage_type' => $usageType?->value,
                        'is_active' => true,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ];

                } catch (Throwable $e) {
                    $errors[] = [
                        'row' => $rowNum + 2,
                        'field' => 'general',
                        'value' => '',
                        'error' => 'Error processing row: '.$e->getMessage(),
                    ];
                    $skippedRows++;

                    continue;
                }

                if (count($buffer) >= self::BATCH_SIZE) {
                    $this->flushBuffer($buffer, $importedRows, $errors);

                    // Update progress every batch
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));

                    $batch->update([
                        'processed_rows' => $processedRows,
                        'imported_rows' => $importedRows,
                        'skipped_rows' => $skippedRows,
                    ]);
                }

                // Update progress every 100 rows for responsiveness
                if ($processedRows % 100 === 0) {
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));
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
                    'error_breakdown' => [
                        'validation_errors' => count(array_filter($errors, fn ($e) => isset($e['field']) && $e['field'] !== 'database_insert')),
                        'database_errors' => count(array_filter($errors, fn ($e) => isset($e['field']) && $e['field'] === 'database_insert')),
                        'general_errors' => count(array_filter($errors, fn ($e) => isset($e['field']) && $e['field'] === 'general')),
                    ],
                ],
            ]);

            // Complete progress tracking
            $this->completeProgress("Upload selesai! {$importedRows} data berhasil diimpor".
                (count($errors) > 0 ? ', '.count($errors).' error ditemukan' : ''));

        } catch (Throwable $e) {
            \Log::error('ProcessFinancingMasterUploadJob: Critical failure', [
                'batch_id' => $this->batchId,
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'processed_rows' => $processedRows ?? 0,
                'imported_rows' => $importedRows ?? 0,
            ]);

            $batch->update([
                'status' => UploadBatchStatus::Failed,
                'error_summary' => [
                    [
                        'row' => 'system',
                        'field' => 'critical_error',
                        'value' => '',
                        'error' => $e->getMessage(),
                        'details' => [
                            'error_code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'processed_rows' => $processedRows ?? 0,
                            'imported_rows' => $importedRows ?? 0,
                        ],
                    ],
                ],
                'progress_log' => [
                    'failed_at' => now()->toDateTimeString(),
                    'error_type' => 'critical_system_error',
                    'processed_before_failure' => $processedRows ?? 0,
                ],
            ]);

            $this->failProgress("Upload gagal: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Bulk upsert buffer ke tabel financing_accounts.
     */
    private function flushBuffer(array &$buffer, int &$importedRows, array &$errors): void
    {
        if (empty($buffer)) {
            return;
        }

        try {
            // Log pre-insert info
            \Log::info('ProcessFinancingMasterUploadJob: Attempting to insert batch', [
                'batch_id' => $this->batchId,
                'buffer_count' => count($buffer),
            ]);

            DB::table('financing_accounts')->upsert(
                $buffer,
                ['account_number'],
                [
                    'customer_name',
                    'product_code',
                    'akad_code',
                    'office_code',
                    'economic_sector',
                    'usage_type',
                    'is_active',
                    'updated_at',
                ]
            );

            $importedRows += count($buffer);

            \Log::info('ProcessFinancingMasterUploadJob: Batch insert successful', [
                'batch_id' => $this->batchId,
                'inserted_count' => count($buffer),
                'total_imported' => $importedRows,
            ]);

        } catch (Throwable $e) {
            \Log::error('ProcessFinancingMasterUploadJob: Batch insert failed', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'buffer_count' => count($buffer),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            // Try to identify which records caused the issue by inserting one by one
            $successCount = 0;
            foreach ($buffer as $index => $record) {
                try {
                    DB::table('financing_accounts')->upsert(
                        [$record],
                        ['account_number'],
                        [
                            'customer_name',
                            'product_code',
                            'akad_code',
                            'office_code',
                            'economic_sector',
                            'usage_type',
                            'is_active',
                            'updated_at',
                        ]
                    );
                    $successCount++;
                } catch (Throwable $singleError) {
                    $errors[] = [
                        'row' => 'batch_'.($index + 1),
                        'field' => 'database_insert',
                        'value' => $record['account_number'] ?? 'unknown',
                        'error' => $this->sanitizeDbError($singleError),
                    ];

                    \Log::error('ProcessFinancingMasterUploadJob: Single record insert failed', [
                        'batch_id' => $this->batchId,
                        'account_number' => $record['account_number'] ?? 'unknown',
                        'error' => $singleError->getMessage(),
                    ]);
                }
            }

            $importedRows += $successCount;

            if ($successCount === 0) {
                $errors[] = [
                    'row' => 'batch',
                    'field' => 'database_insert',
                    'value' => 'entire_batch',
                    'error' => $this->sanitizeDbError($e),
                ];
            }
        }

        $buffer = [];
    }

    /**
     * Sediakan pesan error yang aman untuk user (tanpa detail internal DB),
     * detail teknis tetap dicatat di log.
     */
    private function sanitizeDbError(Throwable $e): string
    {
        $message = $e->getMessage();

        // Duplikat entry: tampilkan pesan ramah user
        if ($e->getCode() == 23000 || str_contains($message, 'Duplicate entry')) {
            return 'Data duplikat terdeteksi di database.';
        }

        // Detail teknis lain (SQL, driver, dsb.) tidak di-expose ke user
        return 'Terjadi kesalahan database saat menyimpan data. Detail teknis dicatat di log aplikasi.';
    }
}
