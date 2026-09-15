<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\PdNetflowPivotExport;
use App\Models\ExportJob;
use App\Services\PdNetflowDetailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export data PD Netflow pivot ke file Excel (.xlsx) secara async via queue.
 * Ref: PRD Bab 7
 *
 * TUJUAN:
 * Menghasilkan file Excel lengkap berisi seluruh pivot PD Netflow:
 * outstanding per bucket per periode, transition rate, compound rate,
 * compound average, dan proyeksi ke depan — untuk kebutuhan pelaporan dan audit.
 *
 * ALUR KERJA:
 * 1. Update status export_job ke 'processing'.
 * 2. Panggil PdNetflowDetailService::calculate() untuk mendapatkan semua data pivot.
 *    Data yang dihasilkan identik dengan tampilan UI pivot (on-the-fly, tidak dari snapshot).
 * 3. Buat instance PdNetflowPivotExport (Maatwebsite Excel) dengan data pivot lengkap.
 * 4. Simpan file ke storage/app/exports/ dengan nama:
 *    pd_netflow_{calculationPeriod}_{usageType|all}.xlsx
 * 5. Update export_job ke 'done' dengan filename dan file_path, atau 'failed' jika error.
 *
 * PARAMETER:
 *   - exportJobId: ID record di tabel export_jobs untuk tracking status download
 *   - calculationPeriod: periode kalkulasi format yyyymm (mis. '202412')
 *   - usageType: filter segmen ('1', '2', dst.) atau null untuk semua segmen
 *
 * CATATAN:
 *   - tries = 1 (tidak di-retry otomatis — file besar lebih baik user trigger ulang manual)
 *   - timeout = 600 detik (10 menit) untuk mengakomodasi dataset besar
 *   - File Excel menggunakan multi-sheet sesuai jenis data (outstanding, transition, dll.)
 */
class PdNetflowPivotExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private readonly int $exportJobId,
        private readonly string $calculationPeriod,
        private readonly ?string $usageType,
    ) {}

    /**
     * Eksekusi kalkulasi pivot dan simpan file Excel ke storage.
     *
     * Langkah eksekusi:
     * 1. Muat export_job; update status ke 'processing'.
     * 2. Panggil PdNetflowDetailService::calculate($calculationPeriod, $usageType)
     *    untuk mendapatkan semua data pivot (outstanding, transition, compound,
     *    compound_avg, projection, dan daftar periode + bucket masing-masing layer).
     * 3. Buat PdNetflowPivotExport dengan data dari langkah 2.
     * 4. Simpan via Excel::store() ke 'local' disk dengan path:
     *    exports/pd_netflow_{calculationPeriod}_{usageType|all}.xlsx
     * 5. Update export_job ke 'done' (filename + file_path) atau 'failed' jika error.
     *    Tidak melakukan re-throw exception — status 'failed' sudah cukup sebagai penanda.
     */
    public function handle(): void
    {
        $exportJob = ExportJob::findOrFail($this->exportJobId);
        $exportJob->update(['status' => 'processing']);

        try {
            $datasets = [];
            foreach (
                [
                    'Konsolidasi' => null,
                    'Modal Kerja' => '1',
                    'Investasi' => '2',
                    'Konsumtif' => '3',
                ] as $title => $usageType
            ) {
                $datasets[$title] = (new PdNetflowDetailService)->calculate(
                    $this->calculationPeriod,
                    $usageType,
                );
            }

            $usageLabel = $this->usageType ?? 'all';
            $filename = "pd_netflow_{$this->calculationPeriod}_{$usageLabel}.xlsx";
            $storagePath = "exports/{$filename}";

            Excel::store(
                new PdNetflowPivotExport($datasets),
                $storagePath,
                'local',
            );

            $exportJob->update([
                'status' => 'done',
                'filename' => $filename,
                'file_path' => $storagePath,
            ]);
        } catch (\Throwable $e) {
            $exportJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
