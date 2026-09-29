<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Lgd\CollateralShortfall;

use App\Domain\Ckpn\Lgd\Contracts\LgdCalculationMethodInterface;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Enums\UsageType;
use App\Models\Collateral;
use App\Models\FinancingAccount;
use App\Models\FinancingAccountPeriod;

/**
 * Calculates LGD using the Collateral Shortfall method.
 * Ref: PRD Bab 10
 *
 * Formula:
 *   Shortfall = Outstanding Balance - Collateral Net Value
 *   LGD_CS = Shortfall / Outstanding Balance
 *
 * Applies to: kualitas 5 (macet) + WO with collateral pending execution.
 */
final class LgdCollateralShortfallCalculator implements LgdCalculationMethodInterface
{
    /**
     * Hitung LGD CS agregat (rata-rata LGD per akun) untuk satu segmen dan periode.
     * Ref: PRD Bab 10
     *
     * Shortcut yang mengembalikan avg_lgd_rate dari aggregate(calculatePerAccount()).
     * Gunakan calculatePerAccount() + aggregate() langsung bila membutuhkan detail per akun untuk snapshot.
     *
     * @param  string  $calculationPeriod  Format yyyymm, mis. 202412
     * @param  string|null  $officeCode  Kode kantor (level 1 segmentasi); NULL = konsolidasi — Ref: PRD Bab 5
     * @return float LGD rate agregat (0.0–1.0)
     */
    public function calculate(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null): float
    {
        return $this->aggregate($this->calculatePerAccount($usageType, $calculationPeriod, $officeCode))['avg_lgd_rate'];
    }

    /**
     * Agregasi hasil perhitungan per akun menjadi ringkasan segmen.
     * Ref: PRD Bab 10
     *
     * Dipakai oleh job snapshot dan tampilan pivot detail agar konsisten (satu sumber kebenaran).
     * Input berasal dari calculatePerAccount(); output disimpan ke tabel lgd_collateral_shortfall_by_segment_results.
     *
     * Hasil agregasi:
     *   - account_count          : jumlah akun eligible yang masuk perhitungan
     *   - total_outstanding      : total baki debet seluruh akun eligible
     *   - total_collateral_net_value : total nilai agunan bersih (setelah diskon)
     *   - total_shortfall        : total kekurangan agunan (total_outstanding - total_collateral_net_value, min 0)
     *   - avg_lgd_rate           : rata-rata LGD per akun (bukan weighted — simple average)
     *
     * @param  array<int, array{financing_account_id?: int, financing_code?: string|null, outstanding_balance: float, collateral_net_value: float, shortfall: float, lgd_rate: float}>  $accountResults
     * @return array{account_count: int, total_outstanding: float, total_collateral_net_value: float, total_shortfall: float, avg_lgd_rate: float}
     */
    public function aggregate(array $accountResults): array
    {
        $count = count($accountResults);

        return [
            'account_count' => $count,
            // Cast float eksplisit — array_sum([]) mengembalikan int 0.
            'total_outstanding' => (float) array_sum(array_column($accountResults, 'outstanding_balance')),
            'total_collateral_net_value' => (float) array_sum(array_column($accountResults, 'collateral_net_value')),
            'total_shortfall' => (float) array_sum(array_column($accountResults, 'shortfall')),
            'avg_lgd_rate' => $count > 0 ? array_sum(array_column($accountResults, 'lgd_rate')) / $count : 0.0,
        ];
    }

    /**
     * Hitung LGD CS per akun eligible untuk satu segmen dan periode.
     * Ref: PRD Bab 10
     *
     * Kriteria akun eligible (harus memenuhi SEMUA syarat berikut):
     *   1. Kolektibilitas = 5 (macet) ATAU writeoff_status = 'W' (sudah writeoff)
     *   2. Segmen (usage_type) sesuai parameter
     *   3. Akad masuk daftar lgd_rate_akad_codes (kosong = semua akad)
     *   4. Akad 03 (POKPBY): hanya masuk jika maturity_date <= akhir bulan periode (sudah jatuh tempo)
     *   5. WAJIB memiliki minimal 1 agunan aktif (is_active = true) dengan nilai terisi
     *      — tanpa agunan, akun tidak masuk LGD-CS
     *
     * Formula per akun:
     *   collateral_net_value = SUM(nilai agunan aktif, lihat resolveCollateralNetValue)
     *   shortfall            = MAX(0, outstanding_balance - collateral_net_value)
     *   lgd_rate             = MIN(1.0, shortfall / outstanding_balance)
     *
     * Akun dengan outstanding_balance = 0 atau collateral_net_value = 0 dilewati (skip).
     *
     * @param  string  $calculationPeriod  Format yyyymm, mis. 202412
     * @param  string|null  $officeCode  Kode kantor (level 1 segmentasi); NULL = konsolidasi — Ref: PRD Bab 5
     * @return array<int, array{financing_account_id: int, financing_code: string|null, outstanding_balance: float, collateral_net_value: float, shortfall: float, lgd_rate: float}>
     */
    public function calculatePerAccount(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null, ?string $akadCode = null): array
    {
        // Daftar akad eligible dari parameter (kosong = semua akad) — Ref: parameter lgd_rate_akad_codes
        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $usageType->value);

        // Eligible accounts: kualitas 5 OR WO status, WITH active collateral pending execution
        // Ref: PRD Bab 10 - hanya pembiayaan coll 5 dan writeoff yang memiliki nilai agunan
        $uploads = FinancingAccountPeriod::where('period', $calculationPeriod)
            ->where(
                fn ($q) => $q
                    ->where('collectibility', 5)
                    ->orWhere('writeoff_status', 'W')
            )
            ->whereHas('financingAccount', function ($q) use ($usageType, $akadCodes, $officeCode, $akadCode) {
                $q->where('usage_type', $usageType->value)
                    // Segmentasi level 1: pecahan per kode kantor — Ref: PRD Bab 5
                    ->when($officeCode !== null, fn ($w) => $w->where('office_code', $officeCode))
                    // Segmentasi level 2: pecahan per kode akad — Ref: PRD Bab 5
                    ->when($akadCode !== null, fn ($w) => $w->where('akad_code', $akadCode))
                    ->when($akadCodes !== null, fn ($w) => $w->whereIn('akad_code', $akadCodes));
            })
            // Akad 03 hanya jika sudah jatuh tempo pada periode perhitungan
            ->whereNot(function ($q): void {
                $q->whereHas('financingAccount', fn ($fa) => $fa->where('akad_code', '03'))
                    ->where(function ($m): void {
                        $m->whereNull('maturity_date')
                            ->orWhereRaw("maturity_date > LAST_DAY(STR_TO_DATE(CONCAT(period, '01'), '%Y%m%d'))");
                    });
            })
            // HARUS memiliki agunan aktif dengan nilai — hanya inilah yang masuk LGD-CS
            ->whereHas('financingAccount.collaterals', fn ($q) => $q->where('is_active', true))
            ->with('financingAccount')
            ->get();

        $results = [];

        foreach ($uploads as $upload) {
            $account = $upload->financingAccount;
            $outstanding = (float) $upload->outstanding_balance;

            if ($outstanding <= 0) {
                continue;
            }

            // Collateral net value = estimated_sale_value (preferred) or appraisal_value * (1 - discount_rate)
            $collateralNetValue = $this->resolveCollateralNetValue($account, $calculationPeriod);

            // Skip jika tidak memiliki nilai agunan yang bisa dihitung
            // Ref: PRD Bab 10 - hanya pembiayaan dengan nilai agunan yang masuk perhitungan
            if ($collateralNetValue <= 0) {
                continue;
            }

            $shortfall = max(0.0, $outstanding - $collateralNetValue);
            $lgdRate = min(1.0, $shortfall / $outstanding);

            $results[] = [
                'financing_account_id' => $account->id,
                'financing_code' => $account->account_number,
                'outstanding_balance' => $outstanding,
                'collateral_net_value' => $collateralNetValue,
                'shortfall' => $shortfall,
                'lgd_rate' => $lgdRate,
            ];
        }

        return $results;
    }

    /**
     * Hitung total nilai agunan bersih (net collateral value) untuk satu akun.
     * Ref: PRD Bab 10.2
     *
     * Hanya agunan dengan is_active = true yang diperhitungkan.
     *
     * Prioritas nilai per agunan:
     *   1. estimated_sale_value  → nilai estimasi penjualan (sudah net biaya, dipakai langsung)
     *   2. appraisal_value × (1 − liquidation_discount_rate)
     *      → nilai appraisal dikurangi diskon likuidasi dari tipe agunan
     *   Jika keduanya NULL → nilai agunan tsb = 0 (tidak dikontribusikan)
     *
     * Contoh:
     *   - Agunan A: estimated_sale_value = 100.000.000 → kontribusi = 100.000.000
     *   - Agunan B: appraisal_value = 200.000.000, discount_rate = 0.20 → kontribusi = 160.000.000
     *   - Total net value = 260.000.000
     *
     * @return float Total nilai agunan bersih (>= 0)
     */
    private function resolveCollateralNetValue(FinancingAccount $account, string $calculationPeriod): float
    {
        $collaterals = Collateral::where('financing_account_id', $account->id)
            ->where('is_active', true)
            ->with('collateralType')
            ->get();

        $totalNetValue = 0.0;

        foreach ($collaterals as $collateral) {
            $discountRate = (float) ($collateral->collateralType?->liquidation_discount_rate ?? 0.0);

            if ($collateral->estimated_sale_value !== null) {
                // Nilai estimasi sudah net biaya, tidak perlu dikali discount rate lagi — Ref: PRD Bab 10.2
                $totalNetValue += (float) $collateral->estimated_sale_value;
            } elseif ($collateral->appraisal_value !== null) {
                $totalNetValue += (float) $collateral->appraisal_value * (1 - $discountRate);
            }
        }

        return $totalNetValue;
    }
}
