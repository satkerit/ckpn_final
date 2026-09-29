<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\PdNetflowDetailBreakdownExport;
use App\Models\ExportJob;
use App\Models\PdNetflowDetailBreakdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export data PD Netflow Detail Breakdown ke file Excel (.xlsx) secara async via queue.
 * Ref: PRD Bab 7
 *
 * TUJUAN:
 * Menghasilkan file Excel dari tabel pd_netflow_detail_breakdown yang sudah disimpan (snapshot),
 * difilter sesuai parameter user (run_log_id, usage_type, office_code, akad_code, bucket_id, period).
 *
 * ALUR KERJA:
 * 1. Update status export_job ke 'processing'.
 * 2. Query PdNetflowDetailBreakdown dengan filter dari params.
 * 3. Buat instance PdNetflowDetailBreakdownExport dengan data hasil query.
 * 4. Simpan file ke storage/app/exports/ dengan nama:
 *    pd_netflow_detail_breakdown_{runLogId|all}_{filters}.xlsx
 * 5. Update export_job ke 'done' dengan filename dan file_path, atau 'failed' jika error.
 *
 * PARAMETER (via $filters array):
 *   - run_log_id (int|null)
 *   - usage_type (int|null)
 *   - office_code (string|null)
 *   - akad_code (string|null)
 *   - bucket_id (int|null)
 *   - period (string|null)
 *
 * CATATAN:
 *   - tries = 1 (tidak di-retry otomatis — file besar lebih baik user trigger ulang manual)
 *   - timeout = 600 detik (10 menit) untuk mengakomodasi dataset besar
 */
class PdNetflowDetailBreakdownExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private readonly int $exportJobId,
        private readonly array $filters,
    ) {}

    /**
     * Eksekusi query data snapshot dan simpan file Excel ke storage.
     */
    public function handle(): void
    {
        $exportJob = ExportJob::findOrFail($this->exportJobId);
        $exportJob->update(['status' => 'processing']);

        try {
            $query = PdNetflowDetailBreakdown::query()
                ->with(['calculationRunLog', 'bucket']);

            // Apply filters
            if (! empty($this->filters['run_log_id'])) {
                $query->where('calculation_run_log_id', $this->filters['run_log_id']);
            }
            if (! empty($this->filters['usage_type'])) {
                $query->where('usage_type', $this->filters['usage_type']);
            }
            if (! empty($this->filters['office_code'])) {
                $query->where('office_code', $this->filters['office_code']);
            }
            if (! empty($this->filters['akad_code'])) {
                $query->where('akad_code', $this->filters['akad_code']);
            }
            if (! empty($this->filters['bucket_id'])) {
                $query->where('from_bucket_id', $this->filters['bucket_id']);
            }
            if (! empty($this->filters['period'])) {
                $query->where('period', $this->filters['period']);
            }

            $records = $query->orderByDesc('created_at')->get();

            $runLogLabel = $this->filters['run_log_id'] ?? 'all';
            $usageLabel = $this->filters['usage_type'] ?? 'all';
            $officeLabel = $this->filters['office_code'] ?? 'all';
            $akadLabel = $this->filters['akad_code'] ?? 'all';
            $filename = "pd_netflow_detail_breakdown_run{$runLogLabel}_ut{$usageLabel}_of{$officeLabel}_ak{$akadLabel}.xlsx";
            $storagePath = "exports/{$filename}";

            Excel::store(
                new PdNetflowDetailBreakdownExport($records),
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
