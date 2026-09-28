<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UploadBatchStatus;
use App\Enums\UsageType;
use App\Models\FinancingUploadBatch;
use App\Traits\HasProgressTracking;
use App\Traits\StreamableExcelUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service untuk memproses upload data secara langsung (synchronous/streaming)
 * tanpa memerlukan antrian queue worker (cocok untuk shared hosting tanpa terminal).
 *
 * Menggunakan OpenSpout streaming reader: memori konstan ~30-50MB untuk file besar.
 *
 * Ref: PRD Bab 3 — Upload Data Pembiayaan & AGENTS.md
 */
class UploadProcessorService
{
    use HasProgressTracking, StreamableExcelUpload;

    protected int $batchId = 0;

    /**
     * Jalankan proses parsing dan impor file langsung secara streaming.
     */
    public function process(int $batchId, string $filePath, string $uploadType): void
    {
        $this->batchId = $batchId;
        $batch = FinancingUploadBatch::find($batchId);
        if ($batch === null) {
            return;
        }

        // Idempotency guard (Ref: PRD Bab 16)
        if ($batch->status === UploadBatchStatus::Done) {
            return;
        }

        if (! file_exists($filePath)) {
            $this->initializeProgress((string) $batchId);
            $this->markFailed($batch, ['File tidak ditemukan di penyimpanan server: '.basename($filePath)]);

            return;
        }

        $batch->update(['status' => UploadBatchStatus::Processing]);

        // Tingkatkan batas eksekusi & memori untuk pemrosesan file besar pada shared hosting
        @ini_set('max_execution_time', '1800');
        @ini_set('memory_limit', '512M');
        if (function_exists('set_time_limit')) {
            @set_time_limit(1800);
        }

        // Scan cepat untuk hitung total baris — dipakai sebagai totalSteps agar progress bar akurat
        try {
            $countReader = $this->createReader($filePath);
            $totalRows = $this->countTotalRows($countReader);
            $this->closeReader($countReader);
        } catch (Throwable $e) {
            Log::error('Upload processing failed (unreadable file)', [
                'batch_id' => $batchId,
                'upload_type' => $uploadType,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->markFailed($batch, ['File tidak dapat dibaca: '.$e->getMessage()]);
            throw $e;
        }

        $this->initializeProgress((string) $batchId, $totalRows);

        try {
            match ($uploadType) {
                'active_financing' => $this->processActiveFinancing($batch, $filePath),
                'historical_financing' => $this->processHistoricalFinancing($batch, $filePath),
                'collateral' => $this->processCollateral($batch, $filePath),
                'financing_office' => $this->processFinancingOffice($batch, $filePath),
                'collateral_type' => $this->processCollateralType($batch, $filePath),
                default => throw new \InvalidArgumentException("Unknown upload_type: {$uploadType}"),
            };
        } catch (Throwable $e) {
            Log::error('Upload processing failed', [
                'batch_id' => $batchId,
                'upload_type' => $uploadType,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->markFailed($batch, [$e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 1. MASTER PEMBIAYAAN AKTIF (financing_accounts)
     */
    protected function processActiveFinancing(FinancingUploadBatch $batch, string $filePath): void
    {
        $this->updateProgress([
            'status_title' => 'Membaca file master pembiayaan...',
            'status_text' => 'Menganalisis baris data',
            'file_info' => basename($filePath).' ('.$this->formatFileSize(filesize($filePath)).')',
        ]);

        $reader = $this->createReader($filePath);
        $headingMap = $this->extractHeadings($reader);
        $this->closeReader($reader);

        $reader = $this->createReader($filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $processedRows = 0;
        $errors = [];
        $buffer = [];
        $batchSize = 1000;

        $colAccount = $this->resolveColIndex($headingMap, ['account_number', 'no_kontrak', 'nomor_kontrak', 'nokontrak']);
        $colCustomer = $this->resolveColIndex($headingMap, ['customer_name', 'nama_nasabah', 'nama']);
        $colProduct = $this->resolveColIndex($headingMap, ['product_code', 'kode_produk', 'produk']);
        $colAkad = $this->resolveColIndex($headingMap, ['akad_code', 'kode_akad', 'akad']);
        $colOffice = $this->resolveColIndex($headingMap, ['office_code', 'kode_kantor', 'kantor']);
        $colSector = $this->resolveColIndex($headingMap, ['economic_sector', 'sektor_ekonomi', 'sektor']);
        $colUsage = $this->resolveColIndex($headingMap, ['usage_type', 'tujuan_penggunaan', 'tujuan']);

        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            // Proteksi per baris: error pada satu baris TIDAK menghentikan upload,
            // baris bermasalah dilewati lalu lanjut ke baris berikutnya.
            try {
                $accountNumber = $this->parseString($this->getCellValue($rowData, $colAccount));
                if ($accountNumber === '') {
                    $skippedRows++;
                    $errors[] = [
                        'row' => $rowNum + 1,
                        'field' => 'account_number',
                        'error' => 'Nomor kontrak tidak boleh kosong',
                    ];

                    continue;
                }

                $usageTypeRaw = $this->getCellValue($rowData, $colUsage);
                $usageType = null;
                if ($usageTypeRaw !== null && $usageTypeRaw !== '') {
                    $usageType = UsageType::tryFrom((int) $usageTypeRaw);
                }

                $customerName = $this->parseString($this->getCellValue($rowData, $colCustomer));
                $productCode = $this->parseString($this->getCellValue($rowData, $colProduct));
                $akadCode = $this->parseString($this->getCellValue($rowData, $colAkad));
                $officeCode = $this->parseString($this->getCellValue($rowData, $colOffice));
                $sector = $this->parseString($this->getCellValue($rowData, $colSector));

                $buffer[] = [
                    'account_number' => $accountNumber,
                    'customer_name' => $customerName !== '' ? mb_substr($customerName, 0, 255) : null,
                    'product_code' => $productCode !== '' ? mb_substr($productCode, 0, 50) : null,
                    'akad_code' => $akadCode !== '' ? mb_substr($akadCode, 0, 50) : null,
                    'office_code' => $officeCode !== '' ? mb_substr($officeCode, 0, 20) : null,
                    'economic_sector' => $sector !== '' ? mb_substr($sector, 0, 100) : null,
                    'usage_type' => $usageType?->value,
                    'is_active' => true,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];

                if (count($buffer) >= $batchSize) {
                    $this->flushActiveFinancingBuffer($buffer, $importedRows, $errors);
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));
                }

                if ($processedRows % 250 === 0) {
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));
                }
            } catch (Throwable $e) {
                $skippedRows++;
                $this->recordRowFailure($errors, $rowNum + 1, $e);
            }
        }

        if (! empty($buffer)) {
            $this->flushActiveFinancingBuffer($buffer, $importedRows, $errors);
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

        $this->completeProgress("Upload master pembiayaan selesai! {$importedRows} data berhasil diimpor.");
    }

    private function flushActiveFinancingBuffer(array &$buffer, int &$importedRows, array &$errors): void
    {
        if (empty($buffer)) {
            return;
        }

        try {
            DB::table('financing_accounts')->upsert(
                $buffer,
                ['account_number'],
                ['customer_name', 'product_code', 'akad_code', 'office_code', 'economic_sector', 'usage_type', 'is_active', 'updated_at']
            );
            $importedRows += count($buffer);
        } catch (Throwable) {
            foreach ($buffer as $item) {
                try {
                    DB::table('financing_accounts')->upsert(
                        [$item],
                        ['account_number'],
                        ['customer_name', 'product_code', 'akad_code', 'office_code', 'economic_sector', 'usage_type', 'is_active', 'updated_at']
                    );
                    $importedRows++;
                } catch (Throwable $ex) {
                    $errors[] = [
                        'row' => null,
                        'field' => 'account_number',
                        'error' => $ex->getMessage(),
                        'value' => $item['account_number'] ?? '',
                    ];
                }
            }
        }

        $buffer = [];
    }

    /**
     * 2. HISTORIS PEMBIAYAAN PER PERIODE (financing_account_periods)
     *
     * Persyaratan Kunci:
     *   - Strict deduplikasi: Tidak ada nokontrak yang sama pada periode yang sama.
     *   - Memory-efficient streaming OpenSpout.
     *   - Bulk upsert/insert berkecepatan tinggi tanpa memory leak.
     */
    protected function processHistoricalFinancing(FinancingUploadBatch $batch, string $filePath): void
    {
        $this->updateProgress([
            'status_title' => 'Mempersiapkan data historis pembiayaan...',
            'status_text' => 'Membangun indeks pencarian akun',
            'file_info' => basename($filePath).' ('.$this->formatFileSize(filesize($filePath)).')',
        ]);

        // Preload mapping account_number -> id untuk lookup cepat O(1)
        $accountCache = DB::table('financing_accounts')
            ->pluck('id', 'account_number')
            ->map(fn ($id) => (int) $id)
            ->all();

        $reader = $this->createReader($filePath);
        $headingMap = $this->extractHeadings($reader);
        $this->closeReader($reader);

        $reader = $this->createReader($filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $skippedNotFound = 0;
        $duplicateRows = 0;
        $processedRows = 0;
        $errors = [];
        $buffer = [];
        $batchSize = 2000; // Batch aman & cepat untuk memory & query placeholder limits

        // In-memory set untuk mencegah duplikasi di dalam file yang sama
        $seenFileKeys = [];

        $colNokontrak = $this->resolveColIndex($headingMap, ['nokontrak', 'no_kontrak', 'nomor_kontrak', 'account_number']);
        $colPeriode = $this->resolveColIndex($headingMap, ['periode', 'period']);
        $colOsmdlc = $this->resolveColIndex($headingMap, ['osmdlc', 'outstanding', 'os']);
        $colPpka = $this->resolveColIndex($headingMap, ['ppka']);
        $colColbaru = $this->resolveColIndex($headingMap, ['colbaru', 'collectibility', 'kol']);
        $colTgkhari = $this->resolveColIndex($headingMap, ['tgkhari', 'hari_tunggakan']);
        $colTgkmdl = $this->resolveColIndex($headingMap, ['tgkmdl', 'tunggakan_modal']);
        $colTglwo = $this->resolveColIndex($headingMap, ['tglwo', 'tanggal_wo', 'writeoff_date']);
        $colStsrec = $this->resolveColIndex($headingMap, ['stsrec', 'status_rekening']);
        $colStsacc = $this->resolveColIndex($headingMap, ['stsacc', 'status_akun']);
        $colTgleff = $this->resolveColIndex($headingMap, ['tgleff', 'tanggal_efektif', 'origination_date']);
        $colTglexp = $this->resolveColIndex($headingMap, ['tglexp', 'tanggal_jatuh_tempo', 'maturity_date']);

        $detectedPeriod = null;

        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            // Proteksi per baris: error pada satu baris TIDAK menghentikan upload,
            // baris bermasalah dilewati lalu lanjut ke baris berikutnya.
            try {
                $accountNumber = $this->parseString($this->getCellValue($rowData, $colNokontrak));
                $period = $this->parseString($this->getCellValue($rowData, $colPeriode));

                if ($accountNumber === '' || $period === '') {
                    $skippedRows++;

                    continue;
                }

                if ($detectedPeriod === null) {
                    $detectedPeriod = $period;
                }

                $accountId = $accountCache[$accountNumber] ?? null;
                if ($accountId === null) {
                    $skippedNotFound++;
                    $skippedRows++;

                    continue;
                }

                // DEDUP RULE 1: Cek duplikasi nokontrak + periode di dalam file yang sedang di-upload
                $uniqueKey = $accountId.'|'.$period;
                if (isset($seenFileKeys[$uniqueKey])) {
                    $duplicateRows++;
                    $skippedRows++;

                    continue;
                }
                $seenFileKeys[$uniqueKey] = true;

                $tgkhari = $this->parseInt($this->getCellValue($rowData, $colTgkhari), 0, 65535);
                $tgkmdlRaw = $this->getCellValue($rowData, $colTgkmdl);
                $tglwoRaw = $this->getCellValue($rowData, $colTglwo);
                $stsrec = $this->parseString($this->getCellValue($rowData, $colStsrec), 'A');
                $stsacc = $this->parseString($this->getCellValue($rowData, $colStsacc));
                $tgleffRaw = $this->getCellValue($rowData, $colTgleff);
                $tglexpRaw = $this->getCellValue($rowData, $colTglexp);
                $ppkaRaw = $this->getCellValue($rowData, $colPpka);

                $buffer[] = [
                    'financing_account_id' => $accountId,
                    'period' => $period,
                    'outstanding_balance' => (float) ($this->getCellValue($rowData, $colOsmdlc, 0)),
                    'ppka' => $ppkaRaw !== null && $ppkaRaw !== '' ? (float) $ppkaRaw : null,
                    'collectibility' => (int) ($this->getCellValue($rowData, $colColbaru, 1)),
                    'tgkhari' => $tgkhari,
                    'tgkmdl' => $tgkmdlRaw !== null && $tgkmdlRaw !== '' ? (float) $tgkmdlRaw : null,
                    'writeoff_date' => $this->parseDate($tglwoRaw),
                    'financing_status' => strtoupper($stsrec),
                    'writeoff_status' => strtoupper($stsacc) === 'W' ? 'W' : null,
                    'origination_date' => $this->parseDate($tgleffRaw),
                    'maturity_date' => $this->parseDate($tglexpRaw),
                    'upload_batch_id' => $batch->id,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];

                if (count($buffer) >= $batchSize) {
                    $this->flushHistoricalFinancingBuffer($buffer, $importedRows, $skippedRows, $duplicateRows, $errors);
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));
                }

                if ($processedRows % 500 === 0) {
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));
                }
            } catch (Throwable $e) {
                $skippedRows++;
                $this->recordRowFailure($errors, $rowNum + 1, $e);
            }
        }

        if (! empty($buffer)) {
            $this->flushHistoricalFinancingBuffer($buffer, $importedRows, $skippedRows, $duplicateRows, $errors);
        }

        $this->closeReader($reader);

        // Bersihkan memori internal cache
        unset($accountCache, $seenFileKeys);

        $errorSummary = [];
        if ($skippedNotFound > 0) {
            $errorSummary[] = "{$skippedNotFound} baris dilewati karena account_number tidak ditemukan di master financing_accounts.";
        }
        if ($duplicateRows > 0) {
            $errorSummary[] = [
                'row' => 'summary',
                'field' => 'duplicate_data',
                'value' => $duplicateRows,
                'error' => "{$duplicateRows} baris duplikat (nokontrak sama pada periode yang sama) dilewati agar data tetap bersih dan akurat.",
            ];
        }

        // Gabungkan error penyimpanan per baris (fallback flush) agar ikut terlaporkan
        $errorSummary = array_merge($errorSummary, $errors);

        $batch->update([
            'period' => $batch->period ?: $detectedPeriod,
            'status' => UploadBatchStatus::Done,
            'total_rows' => $processedRows,
            'imported_rows' => $importedRows,
            'failed_rows' => count($errorSummary),
            'skipped_rows' => $skippedRows,
            'processed_rows' => $processedRows,
            'error_summary' => $errorSummary ?: null,
            'progress_log' => [
                'finished_at' => now()->toDateTimeString(),
                'total' => $processedRows,
                'imported' => $importedRows,
                'skipped' => $skippedRows,
                'skipped_not_found' => $skippedNotFound,
                'skipped_duplicates' => $duplicateRows,
            ],
        ]);

        $this->completeProgress("Upload historis selesai! {$importedRows} baris berhasil diimpor".
            ($duplicateRows > 0 ? ", {$duplicateRows} duplikat dilewati" : '').
            ($skippedNotFound > 0 ? ", {$skippedNotFound} akun tidak ditemukan" : ''));
    }

    /**
     * Flush buffer historis pembiayaan dengan proteksi DEDUP ketat terhadap database existing.
     */
    private function flushHistoricalFinancingBuffer(array &$buffer, int &$importedRows, int &$skippedRows, int &$duplicateRows, array &$errors): void
    {
        if (empty($buffer)) {
            return;
        }

        // DEDUP RULE 2: Cek kombinasi (financing_account_id, period) yang sudah ada di DB
        $accountIds = [];
        $periods = [];
        foreach ($buffer as $row) {
            $accountIds[$row['financing_account_id']] = true;
            $periods[$row['period']] = true;
        }

        $existingInDb = DB::table('financing_account_periods')
            ->whereIn('financing_account_id', array_keys($accountIds))
            ->whereIn('period', array_keys($periods))
            ->select('financing_account_id', 'period')
            ->get()
            ->keyBy(fn ($r) => $r->financing_account_id.'|'.$r->period);

        $toInsert = [];
        foreach ($buffer as $row) {
            $key = $row['financing_account_id'].'|'.$row['period'];
            if ($existingInDb->has($key)) {
                $skippedRows++;
                $duplicateRows++;

                continue;
            }
            $toInsert[] = $row;
        }

        if (! empty($toInsert)) {
            // Bulk insert chunks of 500 rows to prevent SQL placeholder limit issues
            $chunks = array_chunk($toInsert, 500);
            foreach ($chunks as $chunk) {
                try {
                    DB::table('financing_account_periods')->insert($chunk);
                    $importedRows += count($chunk);
                } catch (Throwable) {
                    // Fallback per baris: baris bermasalah dilewati, sisanya tetap masuk
                    foreach ($chunk as $singleRow) {
                        try {
                            DB::table('financing_account_periods')->insert($singleRow);
                            $importedRows++;
                        } catch (Throwable $ex) {
                            $skippedRows++;
                            $duplicateRows++;
                            $errors[] = [
                                'row' => null,
                                'field' => 'financing_account_id + period',
                                'error' => $ex->getMessage(),
                                'value' => $singleRow['financing_account_id'].'|'.$singleRow['period'],
                            ];
                        }
                    }
                }
            }
        }

        $buffer = [];
    }

    /**
     * 3. DATA JAMINAN / AGUNAN (collaterals)
     */
    protected function processCollateral(FinancingUploadBatch $batch, string $filePath): void
    {
        $this->updateProgress([
            'status_title' => 'Mempersiapkan data jaminan...',
            'status_text' => 'Menganalisis master jaminan',
            'file_info' => basename($filePath).' ('.$this->formatFileSize(filesize($filePath)).')',
        ]);

        $accountCache = DB::table('financing_accounts')
            ->pluck('id', 'account_number')
            ->map(fn ($id) => (int) $id)
            ->all();

        $typeCache = DB::table('collateral_types')
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();

        $reader = $this->createReader($filePath);
        $headingMap = $this->extractHeadings($reader);
        $this->closeReader($reader);

        $reader = $this->createReader($filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $skippedNotFound = 0;
        $skippedInvalidType = 0;
        $duplicateRows = 0;
        $processedRows = 0;
        $errors = [];
        $buffer = [];
        $batchSize = 1000;

        // In-memory dedup: kunci unik (financing_account_id|collateral_code|sequence_number).
        // Satu akun BOLEH punya jaminan dengan collateral_code sama asalkan sequence_number berbeda.
        $seenFileKeys = [];

        $colAccount = $this->resolveColIndex($headingMap, ['account_number', 'nokontrak', 'no_kontrak', 'nomor_kontrak']);
        $colCode = $this->resolveColIndex($headingMap, ['collateral_code', 'kode_agunan', 'kode_jaminan', 'collateral_id', 'id_agunan', 'noreg']);
        $colSeq = $this->resolveColIndex($headingMap, ['sequence_number', 'no_urut', 'seq', 'urut']);
        $colType = $this->resolveColIndex($headingMap, ['collateral_type_code', 'jenis_jaminan', 'jenis_agunan', 'kode_jenis']);
        $colDesc = $this->resolveColIndex($headingMap, ['description', 'keterangan', 'deskripsi', 'nama_jaminan']);
        $colAppraisal = $this->resolveColIndex($headingMap, ['appraisal_value', 'nilai_taksasi', 'nilai_pasar', 'nilai_agunan']);
        $colLiquidation = $this->resolveColIndex($headingMap, ['estimated_sale_value', 'nilai_likuidasi', 'njop']);
        $colAppraisedAt = $this->resolveColIndex($headingMap, ['appraised_at', 'tanggal_taksasi', 'tgl_penilaian', 'tgl_taksasi']);
        $colActive = $this->resolveColIndex($headingMap, ['is_active', 'status_aktif', 'aktif']);

        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            // Proteksi per baris: error pada satu baris TIDAK menghentikan upload,
            // baris bermasalah dilewati lalu lanjut ke baris berikutnya.
            try {
                $accountNumber = $this->parseString($this->getCellValue($rowData, $colAccount));

                // Lewati baris yang tidak punya nomor kontrak
                if ($accountNumber === '') {
                    $skippedRows++;

                    continue;
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

                $collateralCode = $this->parseString($this->getCellValue($rowData, $colCode));

                // Fallback noreg: sertakan sequence_number agar tiap jaminan dalam satu akun tetap unik
                if ($collateralCode === '') {
                    $collateralCode = 'JMN-'.$accountNumber.'-'.$sequenceNumber;
                }

                // Dedup dalam file yang sama: tolak kombinasi identik (account_id|noreg|urut)
                $uniqueKey = $accountId.'|'.$collateralCode.'|'.$sequenceNumber;
                if (isset($seenFileKeys[$uniqueKey])) {
                    $duplicateRows++;
                    $skippedRows++;

                    continue;
                }
                $seenFileKeys[$uniqueKey] = true;

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

                if (count($buffer) >= $batchSize) {
                    $this->flushCollateralBuffer($buffer, $importedRows, $skippedRows, $errors);
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, $skippedNotFound + $skippedInvalidType);
                }

                if ($processedRows % 250 === 0) {
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, $skippedNotFound + $skippedInvalidType);
                }
            } catch (Throwable $e) {
                $skippedRows++;
                $this->recordRowFailure($errors, $rowNum + 1, $e);
            }
        }

        if (! empty($buffer)) {
            $this->flushCollateralBuffer($buffer, $importedRows, $skippedRows, $errors);
        }

        $this->closeReader($reader);
        unset($accountCache, $typeCache);

        $errorSummary = [];
        if ($skippedNotFound > 0) {
            $errorSummary[] = [
                'row' => null,
                'field' => 'account_number',
                'error' => "Nomor rekening tidak ditemukan di data master pembiayaan: {$skippedNotFound} baris dilewati.",
            ];
        }
        if ($skippedInvalidType > 0) {
            $errorSummary[] = [
                'row' => null,
                'field' => 'collateral_type_code',
                'error' => "Kode jenis jaminan tidak ditemukan di master jenis jaminan: {$skippedInvalidType} baris dilewati.",
            ];
        }
        if ($duplicateRows > 0) {
            $errorSummary[] = [
                'row' => null,
                'field' => 'collateral_code + sequence_number',
                'error' => "Kombinasi noreg + no urut duplikat dalam file: {$duplicateRows} baris dilewati.",
            ];
        }

        // Gabungkan error penyimpanan per baris (fallback flush) agar ikut terlaporkan
        $errorSummary = array_merge($errorSummary, $errors);

        $batch->update([
            'status' => UploadBatchStatus::Done,
            'total_rows' => $processedRows,
            'imported_rows' => $importedRows,
            'failed_rows' => $skippedNotFound + $skippedInvalidType + $duplicateRows + count($errors),
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
                'skipped_duplicates' => $duplicateRows,
                'failed_insert' => count($errors),
            ],
        ]);

        $this->completeProgress("Upload data jaminan selesai! {$importedRows} data berhasil diimpor.");
    }

    /**
     * Flush buffer jaminan. Kegagalan batch TIDAK menghentikan upload:
     * baris dicoba satu per satu, baris bermasalah dilewati & dicatat.
     */
    private function flushCollateralBuffer(array &$buffer, int &$importedRows, int &$skippedRows, array &$errors): void
    {
        if (empty($buffer)) {
            return;
        }

        try {
            DB::table('collaterals')->upsert(
                $buffer,
                ['financing_account_id', 'collateral_code', 'sequence_number'],
                ['collateral_type_id', 'description', 'appraisal_value', 'estimated_sale_value', 'appraised_at', 'is_active', 'updated_at']
            );
            $importedRows += count($buffer);
        } catch (Throwable) {
            // Fallback: simpan per baris agar satu baris bermasalah tidak membatalkan sisanya
            foreach ($buffer as $item) {
                try {
                    DB::table('collaterals')->upsert(
                        [$item],
                        ['financing_account_id', 'collateral_code', 'sequence_number'],
                        ['collateral_type_id', 'description', 'appraisal_value', 'estimated_sale_value', 'appraised_at', 'is_active', 'updated_at']
                    );
                    $importedRows++;
                } catch (Throwable $ex) {
                    $skippedRows++;
                    $errors[] = [
                        'row' => null,
                        'field' => 'collateral_code',
                        'error' => $ex->getMessage(),
                        'value' => (string) ($item['collateral_code'] ?? ''),
                    ];
                }
            }
        }

        $buffer = [];
    }

    /**
     * 4. MASTER KANTOR CABANG (financing_offices)
     */
    protected function processFinancingOffice(FinancingUploadBatch $batch, string $filePath): void
    {
        $reader = $this->createReader($filePath);
        $headingMap = $this->extractHeadings($reader);
        $this->closeReader($reader);

        $reader = $this->createReader($filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $processedRows = 0;
        $errors = [];
        $buffer = [];
        $batchSize = 500;

        $colCode = $this->resolveColIndex($headingMap, ['code', 'kode_kantor', 'kode', 'office_code']);
        $colName = $this->resolveColIndex($headingMap, ['name', 'nama_kantor', 'nama']);
        $colActive = $this->resolveColIndex($headingMap, ['is_active', 'aktif', 'active']);

        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            // Proteksi per baris: error pada satu baris TIDAK menghentikan upload.
            try {
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

                if (count($buffer) >= $batchSize) {
                    $this->flushOfficeBuffer($buffer, $importedRows, $skippedRows, $errors);
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));
                }
            } catch (Throwable $e) {
                $skippedRows++;
                $this->recordRowFailure($errors, $rowNum + 1, $e);
            }
        }

        if (! empty($buffer)) {
            $this->flushOfficeBuffer($buffer, $importedRows, $skippedRows, $errors);
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

        $this->completeProgress("Upload master kantor selesai! {$importedRows} kantor berhasil diimpor.");
    }

    /**
     * Flush buffer kantor. Kegagalan batch TIDAK menghentikan upload:
     * baris dicoba satu per satu, baris bermasalah dilewati & dicatat.
     */
    private function flushOfficeBuffer(array &$buffer, int &$importedRows, int &$skippedRows, array &$errors): void
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
        } catch (Throwable) {
            // Fallback: simpan per baris agar satu baris bermasalah tidak membatalkan sisanya
            foreach ($buffer as $item) {
                try {
                    DB::table('financing_offices')->upsert(
                        [$item],
                        ['code'],
                        ['name', 'is_active', 'updated_at']
                    );
                    $importedRows++;
                } catch (Throwable $ex) {
                    $skippedRows++;
                    $errors[] = [
                        'row' => null,
                        'field' => 'code',
                        'error' => $ex->getMessage(),
                        'value' => (string) ($item['code'] ?? ''),
                    ];
                }
            }
        }

        $buffer = [];
    }

    /**
     * 5. MASTER JENIS JAMINAN (collateral_types)
     */
    protected function processCollateralType(FinancingUploadBatch $batch, string $filePath): void
    {
        $reader = $this->createReader($filePath);
        $headingMap = $this->extractHeadings($reader);
        $this->closeReader($reader);

        $reader = $this->createReader($filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $processedRows = 0;
        $errors = [];
        $buffer = [];
        $batchSize = 500;

        $colCode = $this->resolveColIndex($headingMap, ['code', 'kode_jaminan', 'kode', 'collateral_type_code']);
        $colName = $this->resolveColIndex($headingMap, ['name', 'nama_jaminan', 'nama']);
        $colRate = $this->resolveColIndex($headingMap, ['liquidation_discount_rate', 'haircut_rate', 'diskon', 'rate']);
        $colActive = $this->resolveColIndex($headingMap, ['is_active', 'aktif', 'active']);

        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            // Proteksi per baris: error pada satu baris TIDAK menghentikan upload.
            try {
                $code = $this->parseString($this->getCellValue($rowData, $colCode));
                if ($code === '') {
                    $skippedRows++;

                    continue;
                }

                $rawActive = $this->getCellValue($rowData, $colActive);
                $isActive = $this->parseBoolean($rawActive, true);

                $rateRaw = $this->getCellValue($rowData, $colRate);
                $rate = $this->parseDecimal($rateRaw);

                if ($rate !== null && ((float) $rate < 0 || (float) $rate > 1)) {
                    $errors[] = [
                        'row' => $rowNum + 1,
                        'field' => 'liquidation_discount_rate',
                        'error' => 'Nilai harus antara 0 dan 1',
                        'value' => (string) $rate,
                    ];
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

                if (count($buffer) >= $batchSize) {
                    $this->flushCollateralTypeBuffer($buffer, $importedRows, $skippedRows, $errors);
                    $this->updateBatchProgress($processedRows, $importedRows, $skippedRows, count($errors));
                }
            } catch (Throwable $e) {
                $skippedRows++;
                $this->recordRowFailure($errors, $rowNum + 1, $e);
            }
        }

        if (! empty($buffer)) {
            $this->flushCollateralTypeBuffer($buffer, $importedRows, $skippedRows, $errors);
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

        $this->completeProgress("Upload master jenis jaminan selesai! {$importedRows} jenis jaminan berhasil diimpor.");
    }

    /**
     * Flush buffer jenis jaminan. Kegagalan batch TIDAK menghentikan upload:
     * baris dicoba satu per satu, baris bermasalah dilewati & dicatat.
     */
    private function flushCollateralTypeBuffer(array &$buffer, int &$importedRows, int &$skippedRows, array &$errors): void
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
        } catch (Throwable) {
            // Fallback: simpan per baris agar satu baris bermasalah tidak membatalkan sisanya
            foreach ($buffer as $item) {
                try {
                    DB::table('collateral_types')->upsert(
                        [$item],
                        ['code'],
                        ['name', 'liquidation_discount_rate', 'is_active', 'updated_at']
                    );
                    $importedRows++;
                } catch (Throwable $ex) {
                    $skippedRows++;
                    $errors[] = [
                        'row' => null,
                        'field' => 'code',
                        'error' => $ex->getMessage(),
                        'value' => (string) ($item['code'] ?? ''),
                    ];
                }
            }
        }

        $buffer = [];
    }

    /**
     * Tandai batch gagal beserta detail penyebabnya.
     *
     * @param  array<int, string|array<string, mixed>>  $errors
     */
    protected function markFailed(FinancingUploadBatch $batch, array $errors): void
    {
        $normalized = [];
        foreach ($errors as $error) {
            $normalized[] = $this->normalizeError($error);
        }

        $batch->update([
            'status' => UploadBatchStatus::Failed,
            'error_summary' => $normalized,
        ]);

        $this->failProgress($this->describeError($normalized[0] ?? 'Upload gagal diproses'));
    }

    /**
     * Catat kegagalan satu baris agar proses upload tetap lanjut ke baris berikutnya.
     * Error dicatat ke $errors (laporan batch) + log aplikasi.
     *
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function recordRowFailure(array &$errors, int $rowNumber, Throwable $e): void
    {
        $message = $e->getMessage();

        $errors[] = [
            'row' => $rowNumber,
            'field' => 'row',
            'error' => $message !== '' ? $message : 'Error tidak diketahui saat memproses baris',
        ];

        Log::warning('Upload row failed, dilanjutkan ke baris berikutnya', [
            'batch_id' => $this->batchId,
            'row' => $rowNumber,
            'error' => $message,
        ]);
    }

    /**
     * Ubah entri error apa pun menjadi array terstruktur ['row', 'field', 'error'].
     *
     * @param  string|array<string, mixed>  $error
     * @return array<string, mixed>
     */
    private function normalizeError(string|array $error): array
    {
        if (is_string($error)) {
            return ['row' => null, 'field' => 'general', 'error' => $error];
        }

        return [
            'row' => $error['row'] ?? null,
            'field' => $error['field'] ?? 'general',
            'error' => (string) ($error['error'] ?? 'Terjadi kesalahan tidak diketahui'),
        ];
    }

    /**
     * Susun teks error yang informatif: sertakan baris & nama kolom bila tersedia.
     *
     * @param  array<string, mixed>  $error
     */
    private function describeError(array $error): string
    {
        $row = $error['row'] ?? null;
        $field = $error['field'] ?? null;
        $message = (string) ($error['error'] ?? 'Terjadi kesalahan tidak diketahui');

        $prefix = '';
        if (is_numeric($row)) {
            $prefix = 'Baris '.$row.': ';
        } elseif ($field !== null && $field !== '' && $field !== 'general') {
            $prefix = 'Kolom '.$field.': ';
        }

        return $prefix.$message;
    }
}
