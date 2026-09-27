<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Enums\UsageType;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
class ProcessFinancingMasterUploadJob extends UploadJobBase
{
    public int $timeout = 600;

    private const int BATCH_SIZE = 1000;

    /**
     * Implementasi parsing master data pembiayaan.
     */
    protected function process(FinancingUploadBatch $batch): void
    {
        $this->updateProgress([
            'status_title' => 'Membaca file...',
            'status_text' => 'Menganalisis struktur file Excel',
            'file_info' => basename($this->filePath).' ('.$this->formatFileSize(filesize($this->filePath)).')',
        ]);

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

        $this->completeProgress("Upload selesai! {$importedRows} data berhasil diimpor".
            (count($errors) > 0 ? ', '.count($errors).' error ditemukan' : ''));
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
        } catch (Throwable $e) {
            Log::error(static::class.': Batch insert failed', [
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

                    Log::error(static::class.': Single record insert failed', [
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

        if ($e->getCode() == 23000 || str_contains($message, 'Duplicate entry')) {
            return 'Data duplikat terdeteksi di database.';
        }

        return 'Terjadi kesalahan database saat menyimpan data. Detail teknis dicatat di log aplikasi.';
    }
}
