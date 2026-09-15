<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UploadBatchStatus;
use App\Imports\CollateralTypeUploadImport;
use App\Models\FinancingUploadBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Job antrian untuk memproses upload Excel master data jenis jaminan (collateral types) secara async.
 *
 * POSISI DALAM ALUR: DATA MASTER — harus diupload SEBELUM data jaminan (collaterals).
 * Tabel collateral_types menyimpan referensi kode + nama jenis jaminan yang dipakai
 * sebagai FK di tabel collaterals. Tanpa master ini, upload jaminan akan gagal validasi FK.
 *
 * FORMAT FILE EXCEL (.xlsx):
 *   Baris 1 = header kolom (mapping di CollateralTypeUploadImport).
 *   Kolom penting:
 *     - code / kode_jaminan  : kode unik jenis jaminan (string, PK logis — upsert by code)
 *     - name / nama_jaminan  : nama deskriptif jenis jaminan (string)
 *     - haircut_rate         : persentase haircut/diskon nilai jaminan (decimal 0–1),
 *                              dipakai oleh LgdCollateralShortfallCalculator untuk menghitung
 *                              Collateral Net Value = collateral_value × (1 − haircut_rate)
 *                              (Ref: PRD Bab 10)
 *     - is_active            : flag aktif/nonaktif jenis jaminan (boolean / 0|1)
 *
 * PROSES:
 *   1. Guard idempotency — skip jika batch sudah Done.
 *   2. Set status → Processing.
 *   3. Delegasi ke CollateralTypeUploadImport (Maatwebsite Excel) yang melakukan
 *      validasi per baris dan upsert ke tabel `collateral_types` (by code).
 *   4. Kumpulkan failures + errors → ringkasan error_summary.
 *   5. Set status → Done. Jika exception fatal → set Failed dan re-throw.
 *
 * ERROR HANDLING: Tidak stop-on-first-error — baris lain tetap diproses walau satu baris gagal.
 *
 * RETRY: tries=3, timeout=300 detik (file master jenis jaminan umumnya kecil).
 *
 * Ref: PRD Bab 10 (LGD-CS, haircut), Bab 15 (tabel collateral_types)
 */
class ProcessCollateralTypeUploadJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $batchId,
        private readonly string $filePath,
    ) {}

    /**
     * Eksekusi utama job upload master jenis jaminan.
     *
     * Langkah:
     *   1. Muat FinancingUploadBatch — findOrFail (batch wajib ada).
     *   2. Guard idempotency: skip tanpa throw jika status sudah Done.
     *   3. Set status → Processing agar UI menampilkan progres.
     *   4. Jalankan CollateralTypeUploadImport via Maatwebsite Excel::import.
     *      Import class menangani validasi + upsert ke tabel collateral_types (by code).
     *   5. Kumpulkan failures (ValidationException per baris dari Maatwebsite)
     *      + errors custom dari import class → gabungkan ke allErrors.
     *   6. Update batch: status=Done, imported_rows, failed_rows, error_summary.
     *      Jika Throwable → set Failed + re-throw (trigger retry Laravel Queue).
     */
    public function handle(): void
    {
        $batch = FinancingUploadBatch::findOrFail($this->batchId);

        // Idempotency guard
        if ($batch->status === UploadBatchStatus::Done) {
            return;
        }

        $batch->update(['status' => UploadBatchStatus::Processing]);

        try {
            $import = new CollateralTypeUploadImport($batch);
            Excel::import($import, $this->filePath, null, \Maatwebsite\Excel\Excel::XLSX);

            $failures = $import->failures();
            $failureMessages = $failures->map(fn ($f) => "Row {$f->row()}: ".implode(', ', $f->errors()))->all();
            $allErrors = array_merge($import->getErrors(), $failureMessages);

            $batch->update([
                'status' => UploadBatchStatus::Done,
                'imported_rows' => $import->getImportedRows(),
                'failed_rows' => count($allErrors),
                'error_summary' => $allErrors ?: null,
            ]);
        } catch (Throwable $e) {
            $batch->update([
                'status' => UploadBatchStatus::Failed,
                'error_summary' => [$e->getMessage()],
            ]);
            throw $e;
        }
    }
}
