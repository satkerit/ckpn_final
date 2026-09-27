<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Job antrian untuk memproses upload Excel data historis pembiayaan per periode secara async.
 *
 * POSISI DALAM ALUR: DATA MASUKAN — harus diupload SETELAH master financing_accounts.
 * Data periode (financing_account_periods) adalah input utama untuk:
 *   - PD Netflow Calculator  : membaca tgkhari (hari tunggakan) & collectibility per periode
 *   - PD Migration Calculator: membaca collectibility antar periode (matrix transisi)
 *   - PopulatePeriodDebtorsJob: mengambil akun aktif periode tertentu sebagai staging CKPN
 *
 * IMPLEMENTASI OPENSPOUT:
 *   - Streaming read tanpa load seluruh file ke memori
 *   - Memory konstan ~30-50MB berapapun ukuran file
 *   - Chunk insert dengan LOAD DATA LOCAL INFILE untuk performa maksimal
 *
 * FORMAT FILE EXCEL (.xlsx):
 *   Baris 1 = header kolom (case-insensitive). Kolom wajib:
 *     - nokontrak   : nomor kontrak pembiayaan (FK ke financing_accounts.account_number)
 *     - periode     : periode dalam format YYYYMM (mis. "202412")
 *   Kolom penting lainnya:
 *     - osmdlc      : outstanding balance (saldo pokok, decimal rupiah)
 *     - colbaru     : collectibility baru (integer 1–5)
 *     - tgkhari     : hari tunggakan (unsigned smallint 0–65535)
 *     - tgkmdl      : tunggakan modal (decimal)
 *     - stsrec      : status rekening ("A"=aktif)
 *     - stsacc      : status akun; "W" → writeoff_status='W'
 *     - tglwo       : tanggal write-off
 *     - tgleff      : tanggal efektif akad
 *     - tglexp      : tanggal jatuh tempo akad
 *
 * Ref: PRD Bab 7 (PD Netflow input), Bab 8 (PD Migration input), Bab 15 (tabel financing_account_periods)
 */
class ProcessFinancingPeriodUploadJob extends UploadJobBase
{
    public int $timeout = 1800; // 30 menit untuk file 300k+ baris

    private const BATCH_SIZE = 20000; // Optimal untuk file besar

    /**
     * Implementasi parsing data historis per periode.
     */
    protected function process(FinancingUploadBatch $batch): void
    {
        $this->initializeProgress((string) $this->batchId);

        // Preload account_number → id cache (1 query, O(1) lookup)
        $accountCache = DB::table('financing_accounts')
            ->pluck('id', 'account_number')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Baca XLSX dengan OpenSpout streaming
        $reader = $this->createReader($this->filePath);
        $headingMap = $this->extractHeadings($reader);

        // Reopen reader untuk iterasi data (OpenSpout perlu close & open ulang)
        $this->closeReader($reader);
        $reader = $this->createReader($this->filePath);

        $importedRows = 0;
        $skippedRows = 0;
        $skippedNotFound = 0;
        $duplicateRows = 0;
        $processedRows = 0;
        $buffer = [];

        // Resolve column indexes
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

        // Stream rows
        foreach ($this->streamRows($reader) as $rowNum => $rowData) {
            $processedRows++;

            $accountNumber = $this->parseString($this->getCellValue($rowData, $colNokontrak));
            $period = $this->parseString($this->getCellValue($rowData, $colPeriode));

            if ($accountNumber === '' || $period === '') {
                $skippedRows++;

                continue;
            }

            $accountId = $accountCache[$accountNumber] ?? null;
            if ($accountId === null) {
                $skippedNotFound++;
                $skippedRows++;

                continue;
            }

            // Sanitasi tgkhari: unsigned smallint (0-65535)
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

            if (count($buffer) >= self::BATCH_SIZE) {
                $this->flushBuffer($buffer, $importedRows, $skippedRows, $duplicateRows, $batch);
            }
        }

        // Flush sisa buffer
        if (! empty($buffer)) {
            $this->flushBuffer($buffer, $importedRows, $skippedRows, $duplicateRows, $batch);
        }

        $this->closeReader($reader);

        // Ringkas error_summary
        $errorSummary = [];
        if ($skippedNotFound > 0) {
            $errorSummary[] = "account_number tidak ditemukan di financing_accounts: {$skippedNotFound} baris di-skip. Upload data master financing_accounts terlebih dahulu.";
        }
        if ($duplicateRows > 0) {
            $errorSummary[] = [
                'row' => 'summary',
                'field' => 'duplicate_data',
                'value' => $duplicateRows,
                'error' => "{$duplicateRows} baris duplikat dilewati (kombinasi nokontrak+periode sudah ada di database atau terduplikasi dalam file). Data existing tidak ditimpa.",
            ];
        }

        $batch->update([
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

        $this->completeProgress("Upload selesai! {$importedRows} baris berhasil diimpor".
            ($skippedNotFound > 0 ? ", {$skippedNotFound} akun tidak ditemukan" : '').
            ($duplicateRows > 0 ? ", {$duplicateRows} duplikat" : ''));
    }

    /**
     * Flush buffer akumulasi baris ke tabel financing_account_periods.
     *
     * Menggunakan LOAD DATA LOCAL INFILE untuk kecepatan maksimal (20-100x lebih cepat
     * dari INSERT batch). Fallback ke INSERT per baris jika LOAD DATA gagal.
     */
    private function flushBuffer(array &$buffer, int &$importedRows, int &$skippedRows, int &$duplicateRows, FinancingUploadBatch $batch): void
    {
        // Cek kombinasi (financing_account_id, period) yang sudah ada di DB
        $accountIds = [];
        $periods = [];
        foreach ($buffer as $row) {
            $accountIds[$row['financing_account_id']] = true;
            $periods[$row['period']] = true;
        }

        $existingKeys = DB::table('financing_account_periods')
            ->whereIn('financing_account_id', array_keys($accountIds))
            ->whereIn('period', array_keys($periods))
            ->select('financing_account_id', 'period')
            ->get()
            ->keyBy(fn ($r) => $r->financing_account_id.'|'.$r->period);

        $seenInBuffer = [];
        $toInsert = [];
        foreach ($buffer as $row) {
            $key = $row['financing_account_id'].'|'.$row['period'];
            if ($existingKeys->has($key) || isset($seenInBuffer[$key])) {
                $skippedRows++;
                $duplicateRows++;

                continue;
            }
            $seenInBuffer[$key] = true;
            $toInsert[] = $row;
        }

        if (! empty($toInsert)) {
            try {
                $this->loadDataInfile($toInsert, $importedRows, $skippedRows);
            } catch (Throwable) {
                // Fallback: insert per baris
                foreach ($toInsert as $row) {
                    try {
                        DB::table('financing_account_periods')->insert($row);
                        $importedRows++;
                    } catch (Throwable) {
                        $skippedRows++;
                    }
                }
            }
        }

        // Simpan progress untuk resume capability
        $batch->update([
            'processed_rows' => $batch->processed_rows + count($buffer),
            'imported_rows' => $importedRows,
            'skipped_rows' => $skippedRows,
        ]);

        $buffer = [];
    }

    /**
     * Gunakan LOAD DATA LOCAL INFILE untuk insert super cepat.
     */
    private function loadDataInfile(array $rows, int &$importedRows, int &$skippedRows): void
    {
        if (empty($rows)) {
            return;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'ckpn_load_');
        $handle = fopen($tempFile, 'w');

        foreach ($rows as $row) {
            $escaped = array_map(function ($value) {
                if ($value === null) {
                    return '\\N';
                }

                return str_replace(["\t", "\n", "\r", '\\'], ['\\t', '\\n', '\\r', '\\\\'], addslashes((string) $value));
            }, array_values($row));
            fputcsv($handle, $escaped, ',', '"', '', true);
        }
        fclose($handle);

        // Escape path: Windows backslash harus dinormalisasi agar MySQL tidak membacanya sebagai escape char.
        $escapedPath = addslashes(str_replace('\\', '/', $tempFile));

        try {
            DB::unprepared("LOAD DATA LOCAL INFILE '{$escapedPath}'
                INTO TABLE financing_account_periods
                FIELDS TERMINATED BY ','
                ENCLOSED BY '\"'
                LINES TERMINATED BY '\n'
                (financing_account_id, period, outstanding_balance, ppka, collectibility, tgkhari, tgkmdl, writeoff_date, financing_status, writeoff_status, origination_date, maturity_date, upload_batch_id, created_at, updated_at)");

            $importedRows += count($rows);
        } catch (Throwable) {
            foreach ($rows as $row) {
                try {
                    DB::table('financing_account_periods')->insert($row);
                    $importedRows++;
                } catch (Throwable) {
                    $skippedRows++;
                }
            }
        }

        unlink($tempFile);
    }
}
