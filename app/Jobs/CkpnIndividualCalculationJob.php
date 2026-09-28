<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Individual\CkpnIndividualCalculator;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\SnapshotWriter;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationGeneralSetting;
use App\Models\CalculationRunLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued job untuk menjalankan perhitungan CKPN Individual.
 * Posisi dalam alur sistem: LANGKAH 4b (paralel dengan CKPN Kolektif, independen).
 * Ref: PRD Bab 6.1
 *
 * KONSEP CKPN INDIVIDUAL:
 * CKPN Individual dihitung per akun untuk debitur yang diklasifikasi sebagai 'individual'
 * (NPL signifikan atau masuk top-N outstanding terbesar). Berbeda dengan kolektif,
 * CKPN Individual menggunakan pendekatan nilai agunan (collateral-based), bukan PD × LGD.
 *
 * Formula per akun:
 *   Collateral Net Value = estimated_sale_value (jika ada)
 *                          ATAU appraisal_value × (1 − discount_rate)
 *   Net Collateral After Selling Cost = collateral_net_value × (1 − selling_cost_rate)
 *   CKPN = max(0, outstanding_balance − net_collateral_after_selling_cost)
 *
 * PARAMETER KONFIGURASI (dari tabel calculation_parameters):
 *   - ckpn_individual_selling_cost_rate: biaya penjualan agunan sebagai persentase
 *     nilai jual bersih (default: 0.05 = 5%).
 *     Priority: usage_type-specific > all-account (usage_type IS NULL).
 *
 * KRITERIA POPULASI AKUN:
 *   - Diklasifikasi sebagai 'individual' di ckpn_period_classifications
 *   - Akad eligible sesuai AkadEligibilityService::KEY_CKPN
 *   - Akad 03 (Musyarakah) hanya jika sudah JTP
 *   - Klasifikasi 'individual' ditetapkan oleh ClassifyPeriodDataJob berdasarkan:
 *     collectibility ≥ npl_min_collectibility ATAU masuk top-N outstanding terbesar
 *
 * OUTPUT:
 *   Snapshot per akun disimpan ke tabel ckpn_individual_results
 *   via SnapshotWriter::writeCkpnIndividualResults().
 *   Kolom utama: account_number, outstanding_balance, collateral_net_value,
 *   selling_cost_rate, ckpn_amount.
 *
 * IDEMPOTENCY:
 *   Job di-skip tanpa error jika run_log sudah berstatus Completed atau Approved.
 *   Max retry: 3x, timeout: 300 detik.
 */
class CkpnIndividualCalculationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $runLogId,
        private readonly int $usageType,
        private readonly string $calculationPeriod,
        /** NULL = semua kantor; 'xxx' = pecahan per kode kantor (level 1 segmentasi) */
        private readonly ?string $officeCode = null,
    ) {}

    /**
     * Eksekusi perhitungan CKPN Individual per akun dan simpan snapshot hasilnya.
     *
     * Langkah eksekusi:
     * 1. Muat run_log dari DB; validasi idempotency (skip jika Completed/Approved).
     * 2. Baca parameter ckpn_individual_selling_cost_rate dari calculation_parameters
     *    (default 0.05 = 5%). Priority: usage_type-specific > all-account.
     * 3. Bangun CkpnIndividualCalculator dengan sellingCostRate.
     * 4. Panggil calculatePerAccount() — mengambil akun dari ckpn_period_classifications
     *    dengan classification='individual', join collaterals untuk nilai agunan.
     *    Setiap baris output berisi: account_number, outstanding_balance,
     *    collateral_net_value, selling_cost_rate, ckpn_amount.
     * 5. Bangun catatan (notes) dengan ringkasan sumber data, filter, dan angka total.
     * 6. Tulis snapshot ke ckpn_individual_results via SnapshotWriter::writeCkpnIndividualResults().
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
            // Ambil parameter dari calculation_general_settings — Ref: AGENTS.md §9
            $sellingCostRate = CalculationGeneralSetting::floatValue('ckpn_individual_selling_cost_rate', 0.05);

            $calculator = new CkpnIndividualCalculator(sellingCostRate: $sellingCostRate);
            $writer = new SnapshotWriter;

            $results = $calculator->calculatePerAccount($usageType, $this->calculationPeriod, $this->officeCode);

            // Catatan dasar data perhitungan — Ref: instruksi user (notes per baris hasil)
            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_CKPN, $this->usageType);
            $totalOutstanding = (float) array_sum(array_column($results, 'outstanding_balance'));
            $notes = sprintf(
                "Dasar data CKPN Individual [%s]:\n"
                    ."- Sumber: ckpn_period_classifications (classification='individual') + collaterals\n"
                    ."- Filter: usage_type; akad eligible: %s; akad 03 hanya jika JTP; top-N NPL (dari klasifikasi)\n"
                    ."- selling_cost_rate=%.4f (dari parameter)\n"
                    .'- account_count=%d; total_outstanding=%.2f',
                $usageType->label(),
                AkadEligibilityService::formatCodes($akadCodes),
                $sellingCostRate,
                count($results),
                $totalOutstanding,
            );

            $writer->writeCkpnIndividualResults($runLog, $this->calculationPeriod, $results, $notes, $this->officeCode);

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update(['status' => RunStatus::Failed, 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        }
    }
}
