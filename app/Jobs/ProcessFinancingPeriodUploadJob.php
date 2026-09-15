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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Job antrian untuk memproses upload Excel data historis pembiayaan per periode secara async.
 *
 * POSISI DALAM ALUR: DATA MASUKAN — harus diupload SETELAH master financing_accounts.
 * Data periode (financing_account_periods) adalah input utama untuk:
 *   - PD Netflow Calculator  : membaca tgkhari (hari tunggakan) & collectibility per periode
 *   - PD Migration Calculator: membaca collectibility antar periode (matrix transisi)
 *   - PopulatePeriodDebtorsJob: mengambil akun aktif periode tertentu sebagai staging CKPN
 * Tanpa data periode, semua kalkulasi kolektif tidak bisa berjalan. (Ref: PRD Bab 7, 8, 15)
 *
 * CATATAN IMPLEMENTASI: Menggunakan PhpSpreadsheet native (bukan Maatwebsite Excel) agar bisa
 * set setReadDataOnly(true) — skip parsing formula & style cell. Untuk file periode besar
 * (>50.000 baris), perbedaan performa sangat signifikan (bisa 5–10x lebih cepat).
 *
 * FORMAT FILE EXCEL (.xlsx):
 *   Baris 1 = header kolom (case-insensitive). Kolom wajib:
 *     - nokontrak   : nomor kontrak pembiayaan (FK ke financing_accounts.account_number)
 *     - periode     : periode dalam format YYYYMM (mis. "202412")
 *   Kolom penting lainnya:
 *     - osmdlc      : outstanding balance (saldo pokok, decimal rupiah)
 *     - colbaru     : collectibility baru (integer 1–5; 1=Lancar, 2=DPK, 3=KL, 4=D, 5=M)
 *     - tgkhari     : hari tunggakan (unsigned smallint 0–65535; nilai di luar range → null)
 *     - tgkmdl      : tunggakan modal (decimal)
 *     - stsrec      : status rekening ("A"=aktif, selain A = skip di PopulatePeriodDebtors)
 *     - stsacc      : status akun; "W" → writeoff_status='W'
 *     - tglwo       : tanggal write-off (format fleksibel, di-parse oleh parseDate)
 *     - tgleff      : tanggal efektif akad / originasi (dapat berubah karena restrukturisasi)
 *     - tglexp      : tanggal jatuh tempo akad
 *
 * PROSES TEKNIS:
 *   1. Guard idempotency & cek file fisik.
 *   2. Preload account_number → id dari financing_accounts (1 query, O(1) lookup saat iterasi).
 *   3. Baca sheet dengan PhpSpreadsheet (readDataOnly=true), iterasi mulai baris 2.
 *   4. Setiap baris: lookup accountId, sanitasi tgkhari, parse tanggal → buffer.
 *   5. Setiap BATCH_SIZE=1000 baris → flushBuffer (cek duplikat, bulk insert).
 *   6. Update progress_log berkala + finalisasi batch dengan ringkasan statistik.
 *
 * DEDUPLICATION: Insert-only (tidak upsert). Kombinasi (financing_account_id, period) yang
 *   sudah ada di DB atau terduplikasi dalam file di-skip → dicatat di error_summary sebagai info.
 *
 * RETRY: tries=3, timeout=600 detik.
 *
 * Ref: PRD Bab 7 (PD Netflow input), Bab 8 (PD Migration input), Bab 15 (tabel financing_account_periods)
 */
class ProcessFinancingPeriodUploadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly int $batchId,
        private readonly string $filePath,
    ) {}

    /**
     * Eksekusi utama job upload data historis pembiayaan per periode.
     *
     * Langkah:
     *   1. Muat FinancingUploadBatch via find() — jika null (batch sudah dihapus saat job masih
     *      antri), keluar tanpa throw agar tidak menumpuk failed_jobs.
     *   2. Guard idempotency: skip tanpa throw jika status sudah Done.
     *   3. Cek file fisik — jika tidak ada → set Failed tanpa throw (tidak perlu retry).
     *   4. Set status → Processing; preload accountCache (account_number → id, 1 query).
     *   5. Baca XLSX dengan PhpSpreadsheet readDataOnly=true; parse heading row → headingMap.
     *   6. Iterasi baris 2..highestRow:
     *      - Skip baris dengan nokontrak/periode kosong → skippedRows++
     *      - Skip akun tidak ditemukan di accountCache → skippedNotFound++ + skippedRows++
     *      - Sanitasi tgkhari (out-of-range → null), parse tglwo/tgleff/tglexp via parseDate()
     *      - Append ke buffer; setiap BATCH_SIZE=1000 baris → flushBuffer() + update progres
     *   7. Flush sisa buffer; bebaskan memori spreadsheet (disconnectWorksheets + unset).
     *   8. Susun error_summary (ringkasan skippedNotFound + duplicateRows — bukan per baris).
     *   9. Update batch: status=Done + progress_log lengkap (total/imported/skipped/failed).
     *      Jika Throwable → set Failed + re-throw (trigger retry Laravel Queue).
     */
    public function handle(): void
    {
        // Batch bisa saja sudah terhapus (mis. reset data) sementara job masih nanggung di queue —
        // keluar tanpa throw agar tidak menumpuk failed_jobs.
        $batch = FinancingUploadBatch::find($this->batchId);
        if ($batch === null) {
            return;
        }

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
            // Preload account_number → id cache (1 query, O(1) lookup)
            $accountCache = DB::table('financing_accounts')
                ->pluck('id', 'account_number')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Baca XLSX dengan setReadDataOnly = true (skip formula/style, jauh lebih cepat)
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($this->filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $importedRows = 0;
            $skippedRows = 0;
            $skippedNotFound = 0; // akun tidak ditemukan di financing_accounts
            $duplicateRows = 0; // nokontrak+periode sudah ada di DB atau duplikat dalam file
            $processedRows = 0;
            $errors = [];
            $buffer = [];

            // Ambil heading row (baris 1) untuk mapping kolom
            $headings = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $headings[] = strtolower(trim((string) $cell->getValue()));
                }
            }
            $headingMap = array_flip($headings); // heading → kolom index (0-based)

            // Proses data mulai baris 2
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

                $accountNumber = trim((string) ($rowData[$headingMap['nokontrak'] ?? -1] ?? ''));
                $period = trim((string) ($rowData[$headingMap['periode'] ?? -1] ?? ''));

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

                $tgkhariRaw = $rowData[$headingMap['tgkhari'] ?? -1] ?? null;
                // Sanitasi: kolom tgkhari adalah unsigned smallint (0-65535).
                // Excel kadang berisi nilai di luar rentang (negatif/kode aneh) — set null agar
                // tidak membuat seluruh chunk upsert gagal (Ref: PRD Bab 7.3 anomali data quality).
                $tgkhari = is_numeric($tgkhariRaw) ? (int) $tgkhariRaw : null;
                if ($tgkhari !== null && ($tgkhari < 0 || $tgkhari > 65535)) {
                    $tgkhari = null;
                }
                $tgkmdl = $rowData[$headingMap['tgkmdl'] ?? -1] ?? null;
                $tglwo = $rowData[$headingMap['tglwo'] ?? -1] ?? null;
                $stsrec = strtoupper(trim((string) ($rowData[$headingMap['stsrec'] ?? -1] ?? 'A'))) ?: 'A';
                $stsacc = strtoupper(trim((string) ($rowData[$headingMap['stsacc'] ?? -1] ?? '')));

                // Tanggal akad / jatuh tempo (tgleff/tglexp) — simpan per baris periode (dapat berubah
                // karena restrukturisasi, Ref: PRD Bab 15)
                $tgleff = $this->parseDate($rowData[$headingMap['tgleff'] ?? -1] ?? null);
                $tglexp = $this->parseDate($rowData[$headingMap['tglexp'] ?? -1] ?? null);

                $buffer[] = [
                    'financing_account_id' => $accountId,
                    'period' => $period,
                    'outstanding_balance' => (float) ($rowData[$headingMap['osmdlc'] ?? -1] ?? 0),
                    'collectibility' => (int) ($rowData[$headingMap['colbaru'] ?? -1] ?? 1),
                    'tgkhari' => $tgkhari,
                    'tgkmdl' => $tgkmdl !== null && $tgkmdl !== '' ? (float) $tgkmdl : null,
                    'writeoff_date' => $this->parseDate($tglwo),
                    'financing_status' => $stsrec,
                    'writeoff_status' => $stsacc === 'W' ? 'W' : null,
                    'origination_date' => $tgleff,
                    'maturity_date' => $tglexp,
                    'upload_batch_id' => $batch->id,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];

                if (count($buffer) >= self::BATCH_SIZE) {
                    $this->flushBuffer($buffer, $importedRows, $skippedRows, $duplicateRows);
                    $batch->update([
                        'processed_rows' => $processedRows,
                        'imported_rows' => $importedRows,
                        'skipped_rows' => $skippedRows,
                    ]);
                }
            }

            // Flush sisa buffer
            if (! empty($buffer)) {
                $this->flushBuffer($buffer, $importedRows, $skippedRows, $duplicateRows);
            }

            // Bebaskan memory spreadsheet
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            // Ringkas error_summary — jangan simpan ribuan baris skip sebagai error
            $errorSummary = $errors ?: null;
            if ($skippedNotFound > 0) {
                $notFoundMsg = "account_number tidak ditemukan di financing_accounts: {$skippedNotFound} baris di-skip. Upload data master financing_accounts terlebih dahulu.";
                $errorSummary = $errorSummary
                    ? array_merge([$notFoundMsg], $errorSummary)
                    : [$notFoundMsg];
            }
            if ($duplicateRows > 0) {
                $dupMsg = "Duplikat di-skip (nokontrak+periode sudah ada di database atau terduplikasi dalam file): {$duplicateRows} baris. Data existing tidak ditimpa.";
                $errorSummary = $errorSummary
                    ? array_merge([$dupMsg], $errorSummary)
                    : [$dupMsg];
            }

            $batch->update([
                'status' => UploadBatchStatus::Done,
                'total_rows' => $processedRows,
                'imported_rows' => $importedRows,
                'failed_rows' => count($errors),
                'skipped_rows' => $skippedRows,
                'processed_rows' => $processedRows,
                'error_summary' => $errorSummary,
                'progress_log' => [
                    'finished_at' => now()->toDateTimeString(),
                    'total' => $processedRows,
                    'imported' => $importedRows,
                    'skipped' => $skippedRows,
                    'skipped_not_found' => $skippedNotFound,
                    'skipped_duplicates' => $duplicateRows,
                    'failed' => count($errors),
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
     * Flush buffer akumulasi baris ke tabel financing_account_periods secara bulk insert.
     *
     * Strategi deduplication (insert-only, data existing tidak ditimpa):
     *   1. Kumpulkan semua financing_account_id + period dari buffer (tanpa N+1).
     *   2. 1 query DB: ambil kombinasi (financing_account_id, period) yang sudah ada.
     *   3. Iterasi buffer: baris dengan key sudah ada di DB atau duplikat dalam buffer → skip.
     *   4. Bulk insert sisa baris via DB::table()->insert(). Jika bulk insert gagal (mis. constraint
     *      violation tak terduga) → fallback per-baris agar 1 baris rusak tidak menggagalkan chunk.
     *
     * Dipanggil setiap BATCH_SIZE=1000 baris oleh handle(), dan sekali lagi untuk sisa buffer akhir.
     *
     * @param  array<int, array<string, mixed>>  $buffer  Buffer baris yang siap diinsert (by-ref, dikosongkan setelah flush)
     * @param  int  $importedRows  Counter akumulasi baris berhasil diinsert (by-ref)
     * @param  int  $skippedRows  Counter akumulasi baris diskip (by-ref)
     * @param  int  $duplicateRows  Counter akumulasi baris duplikat (by-ref)
     */
    private function flushBuffer(array &$buffer, int &$importedRows, int &$skippedRows, int &$duplicateRows): void
    {
        // Cek kombinasi (financing_account_id, period) yang sudah ada di DB — 1 query per chunk
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
            // Skip jika sudah ada di database ATAU terduplikasi dalam file ini
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
                DB::table('financing_account_periods')->insert($toInsert);
                $importedRows += count($toInsert);
            } catch (Throwable) {
                // Fallback per-baris: satu baris rusak tidak boleh menggagalkan chunk penuh.
                // Baris invalid di-skip, sisanya tetap masuk.
                foreach ($toInsert as $row) {
                    try {
                        DB::table('financing_account_periods')->insert([$row]);
                        $importedRows++;
                    } catch (Throwable) {
                        $skippedRows++;
                    }
                }
            }
        }
        $buffer = [];
    }

    /**
     * Parse nilai sel Excel menjadi string tanggal format 'Y-m-d', atau null jika tidak valid.
     *
     * Excel menyimpan tanggal dalam beberapa format berbeda — fungsi ini menangani semua kasus:
     *   1. String 8 digit (Ymd) mis. "20241231" → divalidasi regex agar hanya format wajar diterima.
     *      "00000000" atau nilai aneh dari sel kosong Excel → null.
     *   2. Numeric Excel serial date (float/int, range 1–99999) → dikonversi via ExcelDate::excelToDateTimeObject.
     *      Nilai di luar range dianggap bukan tanggal → null.
     *   3. String format lain (mis. "31/12/2024", "2024-12-31") → Carbon::parse sebagai fallback.
     *      Jika parse gagal → null (tangkap Throwable, bukan Exception saja).
     *
     * Sanity check akhir: tahun hasil parse harus dalam rentang 1900–2100. Excel kadang
     * mengkonversi sel kosong menjadi tanggal mustahil seperti '-0001-11-30' — nilai ini dikembalikan null.
     *
     * @param  mixed  $value  Nilai sel mentah dari PhpSpreadsheet (bisa string, float, int, atau null)
     * @return string|null Tanggal dalam format 'Y-m-d', atau null jika tidak valid/kosong
     */
    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $str = trim((string) $value);
        $date = null;
        if (preg_match('/^\d{8}$/', $str)) {
            // Hanya terima Ymd yang wajar — "00000000" dari sel Excel kosong tidak
            $date = preg_match('/^(19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])$/', $str)
                ? Carbon::createFromFormat('Ymd', $str)?->toDateString()
                : null;
        } elseif (is_numeric($value) && $value > 0 && $value < 100000) {
            $date = ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
        } else {
            try {
                $date = Carbon::parse($str)->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        if ($date === null) {
            return null;
        }

        // Sanity check: Excel kadang menyimpan tanggal kosong sebagai 00/00/0000
        // yang terkonversi menjadi nilai mustahil spt '-0001-11-30' → anggap null
        $year = (int) substr($date, 0, 4);

        return ($year >= 1900 && $year <= 2100) ? $date : null;
    }
}
