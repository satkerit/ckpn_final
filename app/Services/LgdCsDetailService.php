<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Ckpn\Lgd\CollateralShortfall\LgdCollateralShortfallCalculator;
use App\Enums\UsageType;
use App\Models\CkpnPeriod;
use App\Models\LgdCollateralShortfallResult;

/**
 * Hitung LGD Collateral Shortfall (CS) detail secara on-the-fly untuk tampilan pivot UI.
 *
 * LGD CS mengukur tingkat kerugian berbasis shortfall agunan:
 *   Shortfall      = max(0, outstanding_balance − collateral_net_value)
 *   LGD Rate (CS)  = Shortfall / outstanding_balance  (jika outstanding > 0, else 0)
 *   Avg LGD Rate   = simple average LGD Rate per segmen (bukan weighted)
 *
 * Kriteria akun eligible untuk LGD CS (PRD Bab 10):
 *   1. Bukan Write-Off (collectibility ≠ 5/WO)
 *   2. Kolektibilitas NPL (≥ ambang batas parameter npl_min_collectibility)
 *   3. Tergolong CKPN Kolektif (classification = 'collective')
 *   4. Agunan aktif (is_active = true) dan valid (appraisal dalam window parameter)
 *
 * Service ini menghitung on-the-fly dari financing_account_periods (bukan snapshot),
 * hasil kalkulasi identik dengan LgdCsCalculationJob namun tidak disimpan ke DB.
 * Gunakan untuk preview/drill-down di UI sebelum finalisasi.
 *
 * Ref: PRD Bab 10
 */
final class LgdCsDetailService
{
    /**
     * Periode CKPN yang tersedia sebagai opsi input perhitungan LGD CS.
     *
     * Sumber data: tabel ckpn_periods (bukan financing_account_periods langsung),
     * karena LGD CS dihitung per calculationPeriod yang terdaftar sebagai periode CKPN resmi.
     * Diurutkan dari periode terbaru ke terlama untuk kemudahan pilihan UI.
     *
     * @return string[] Array periode format yyyymm, mis. ['202506', '202503', '202412']
     */
    public function availablePeriods(): array
    {
        return CkpnPeriod::orderedPeriods();
    }

    /**
     * Periode yang sudah punya snapshot LGD CS tersimpan di tabel lgd_collateral_shortfall_results.
     *
     * Digunakan untuk memfilter pilihan periode di UI tabel hasil — hanya tampilkan periode
     * yang sudah pernah dijalankan job kalkulasi LGD CS (LgdCsCalculationJob).
     * Berbeda dengan availablePeriods() yang menampilkan semua periode CKPN terdaftar.
     *
     * @return string[] Array periode format yyyymm, hanya periode yang sudah ada snapshotnya
     */
    public function snapshotPeriods(): array
    {
        return LgdCollateralShortfallResult::distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period')
            ->toArray();
    }

    /**
     * Hitung pivot detail LGD CS on-the-fly untuk satu periode kalkulasi dan opsional satu segmen.
     *
     * Alur kalkulasi per segmen:
     *   1. Panggil LgdCollateralShortfallCalculator::calculatePerAccount($usageType, $period)
     *      → hasilkan array per akun: outstanding, collateral_net_value, shortfall, lgd_rate
     *   2. Panggil aggregate($accounts) → ringkasan segmen: total + avg_lgd_rate
     *   3. Gabungkan per segmen ke array $segments
     *
     * Parameter:
     *   $calculationPeriod  — periode target format yyyymm (harus terdaftar di ckpn_periods)
     *   $usageTypeValue     — nilai integer UsageType sebagai string, mis. '1' atau '2';
     *                         null = hitung semua segmen (loop seluruh UsageType::cases())
     *
     * Output array per segmen:
     *   usage_type               — enum UsageType (segmen pembiayaan)
     *   label                    — label human-readable segmen
     *   account_count            — jumlah akun eligible di segmen ini
     *   total_outstanding        — total saldo pokok (EAD) segmen
     *   total_collateral_net_value — total nilai agunan bersih (setelah haircut/penyesuaian)
     *   total_shortfall          — total shortfall = max(0, outstanding − collateral_net_value)
     *   avg_lgd_rate             — simple average LGD Rate per segmen (bukan weighted average)
     *   accounts[]               — detail per akun: financing_account_id, financing_code,
     *                              outstanding_balance, collateral_net_value, shortfall, lgd_rate
     *
     * @param  string  $calculationPeriod  Format yyyymm
     * @param  string|null  $usageTypeValue  Integer string UsageType; null = semua segmen
     * @return array{
     *   calculation_period: string,
     *   is_all_segment: bool,
     *   segments: array<int, array{
     *     usage_type: UsageType,
     *     label: string,
     *     account_count: int,
     *     total_outstanding: float,
     *     total_collateral_net_value: float,
     *     total_shortfall: float,
     *     avg_lgd_rate: float,
     *     accounts: array<int, array{
     *       financing_account_id: int,
     *       financing_code: string|null,
     *       outstanding_balance: float,
     *       collateral_net_value: float,
     *       shortfall: float,
     *       lgd_rate: float,
     *     }>,
     *   }>,
     * }
     */
    public function calculate(string $calculationPeriod, ?string $usageTypeValue): array
    {
        $calculator = new LgdCollateralShortfallCalculator;

        $usageTypes = $usageTypeValue !== null
            ? [UsageType::from((int) $usageTypeValue)]
            : UsageType::cases();

        $segments = [];

        foreach ($usageTypes as $usageType) {
            $accounts = $calculator->calculatePerAccount($usageType, $calculationPeriod);

            // Agregasi satu sumber kebenaran dengan snapshot — Ref: PRD Bab 10
            $segments[] = array_merge(
                ['usage_type' => $usageType, 'label' => $usageType->label()],
                $calculator->aggregate($accounts),
                ['accounts' => $accounts],
            );
        }

        return [
            'calculation_period' => $calculationPeriod,
            'is_all_segment' => $usageTypeValue === null,
            'segments' => $segments,
        ];
    }
}
