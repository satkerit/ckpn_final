<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Collective\CkpnCollectiveCalculator;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\SnapshotWriter;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationParameter;
use App\Models\CalculationRunLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued job untuk menjalankan perhitungan CKPN Kolektif.
 * Posisi dalam alur sistem: LANGKAH 4a (setelah LGD Final selesai di LANGKAH 3).
 * Ref: PRD Bab 11
 *
 * KONSEP CKPN KOLEKTIF:
 * CKPN Kolektif dihitung untuk akun yang diklasifikasi sebagai 'collective' oleh
 * ClassifyPeriodDataJob. Formula per akun:
 *   CKPN = EAD × PD × LGD
 *   EAD (Exposure at Default) = outstanding_balance akun
 *   PD  = pd_rate dari snapshot PD Netflow atau PD Migration periode yang sama
 *   LGD = lgd_rate dari snapshot LGD Final periode yang sama (ER atau CS)
 *
 * PARAMETER KONFIGURASI (dari tabel calculation_parameters):
 *   - ckpn_collective_pd_method: 'netflow' (default) atau 'migration'.
 *     Priority: usage_type-specific > all-account. Juga bisa dioverride lewat
 *     constructor param $pdMethod saat dispatch, namun parameter DB lebih diprioritaskan.
 *
 * PRASYARAT:
 *   - ckpn_period_classifications sudah terisi (PopulatePeriodDebtorsJob + ClassifyPeriodDataJob)
 *   - Snapshot PD (Netflow atau Migration) untuk periode & usage_type yang sama sudah ada
 *   - Snapshot LGD Final untuk periode & usage_type yang sama sudah ada
 *   Jika dependensi tidak ada, kalkulasi gagal dan run_log diset ke Failed.
 *
 * OUTPUT:
 *   Snapshot per akun disimpan ke tabel ckpn_collective_results
 *   via SnapshotWriter::writeCkpnCollectiveResults().
 *   Kolom utama: account_number, ead, pd_rate, lgd_rate, ckpn_amount, pd_method.
 *
 * IDEMPOTENCY:
 *   Job di-skip tanpa error jika run_log sudah berstatus Completed atau Approved.
 *   Max retry: 3x, timeout: 600 detik (lebih lama karena volume akun kolektif besar).
 */
class CkpnCollectiveCalculationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        private readonly int $runLogId,
        private readonly int $usageType,
        private readonly string $calculationPeriod,
        /** 'netflow' | 'migration' — PD method yang dipakai untuk usage type ini */
        private readonly string $pdMethod = 'netflow',
    ) {}

    /**
     * Eksekusi perhitungan CKPN Kolektif per akun dan simpan snapshot hasilnya.
     *
     * Langkah eksekusi:
     * 1. Muat run_log dari DB; validasi idempotency (skip jika Completed/Approved).
     * 2. Baca parameter ckpn_collective_pd_method dari calculation_parameters
     *    ('netflow' atau 'migration'). Parameter DB lebih diprioritaskan dari
     *    nilai $pdMethod yang di-pass lewat constructor saat dispatch.
     * 3. Bangun CkpnCollectiveCalculator dengan pd_method yang telah di-resolve.
     * 4. Panggil calculatePerAccount() — mengambil akun dari ckpn_period_classifications
     *    dengan classification='collective', lalu join snapshot PD dan LGD Final
     *    untuk periode yang sama. Formula: CKPN = EAD × PD × LGD.
     *    Setiap baris output berisi: account_number, ead, pd_rate, lgd_rate, ckpn_amount.
     * 5. Bangun catatan (notes) dengan ringkasan sumber data, pd_method, dan angka total EAD.
     * 6. Tulis snapshot ke ckpn_collective_results via SnapshotWriter::writeCkpnCollectiveResults().
     *    Update run_log ke Completed. Jika gagal, update ke Failed dan re-throw exception.
     */
    public function handle(): void
    {
        $runLog = CalculationRunLog::findOrFail($this->runLogId);
        $usageType = UsageType::from($this->usageType);

        // Idempotency guard — Ref: AGENTS.md §4
        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            // Resolve PD method dari parameter jika tidak di-pass eksplisit
            $pdMethod = CalculationParameter::where('parameter_key', 'ckpn_collective_pd_method')
                ->where(fn ($q) => $q->where('usage_type', $this->usageType)->orWhereNull('usage_type'))
                ->orderByRaw('usage_type IS NULL ASC')
                ->value('parameter_value') ?? $this->pdMethod;

            $calculator = new CkpnCollectiveCalculator((string) $pdMethod);
            $writer = new SnapshotWriter;

            $results = $calculator->calculatePerAccount($usageType, $this->calculationPeriod);

            // Catatan dasar data perhitungan — Ref: instruksi user (notes per baris hasil)
            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_CKPN, $this->usageType);
            $totalEad = (float) array_sum(array_column($results, 'ead'));
            $notes = sprintf(
                "Dasar data CKPN Kolektif [%s]:\n"
                    ."- Sumber: ckpn_period_classifications (classification='collective') + pd_result + lgd_result\n"
                    ."- Filter: usage_type; akad eligible: %s; akad 03 hanya jika JTP\n"
                    ."- pd_method=%s; lgd_method dipilih per akun (ER/CS)\n"
                    .'- account_count=%d; total_ead=%.2f',
                $usageType->label(),
                AkadEligibilityService::formatCodes($akadCodes),
                $pdMethod,
                count($results),
                $totalEad,
            );

            $writer->writeCkpnCollectiveResults($runLog, $this->calculationPeriod, $results, $notes);

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update(['status' => RunStatus::Failed, 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        }
    }
}
