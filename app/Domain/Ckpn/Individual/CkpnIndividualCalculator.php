<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Individual;

use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\PokpbyCriteriaService;
use App\Enums\ClassificationType;
use App\Enums\UsageType;
use App\Models\CkpnPeriodClassification;
use App\Models\Collateral;
use App\Models\FinancingAccount;
use App\Models\FinancingAccountPeriod;

/**
 * Calculates CKPN Individual untuk akun yang sudah diklasifikasi individual.
 * Ref: PRD Bab 6.1
 *
 * Formula:
 *   Total Nilai Likuidasi  = SUM(estimated_sale_value) semua jaminan aktif
 *   Biaya Penjualan        = Total Nilai Likuidasi x Rate Biaya Penjualan
 *   CKPN Individual        = Baki Debet - Total Nilai Likuidasi - Biaya Penjualan
 *
 * Sumber akun: ckpn_period_classifications dengan classification = 'individual'.
 */
final class CkpnIndividualCalculator
{
    public function __construct(
        /** Biaya penjualan (%) dikali nilai likuidasi agunan. Dibaca dari parameter ckpn_individual_selling_cost_rate. Ref: PRD Bab 6.1 */
        private readonly float $sellingCostRate = 0.0,
    ) {}

    /**
     * Menghitung CKPN Individual untuk semua akun yang sudah diklasifikasi sebagai "individual" pada periode ini.
     *
     * Kriteria akun yang dihitung:
     * - Terdaftar di tabel `ckpn_period_classifications` dengan classification = 'individual' untuk periode ini.
     * - Termasuk dalam segmen usage_type yang diminta.
     * - Memiliki kode akad yang masuk daftar eligible (parameter `ckpn_eligible_akad_codes`);
     *   jika parameter kosong, semua akad eligible.
     * - Memenuhi kriteria POKPBY jika termasuk dalam daftar khusus (parameter `pokpby_special_criteria_list`).
     *   Contoh: POKPBY=10 harus sudah jatuh tempo.
     *
     * Formula per akun:
     *   Total Nilai Likuidasi = SUM(estimated_sale_value) atau fallback appraisal_value semua jaminan aktif
     *   Biaya Penjualan       = Total Nilai Likuidasi × sellingCostRate
     *   CKPN Individual       = EAD Value − Total Nilai Likuidasi − Biaya Penjualan
     *   EAD Value ditentukan berdasarkan POKPBY:
     *     - outstanding_balance untuk POKPBY umum
     *     - tgkmdl untuk POKPBY tertentu (misal: POKPBY=10)
     *   (CKPN tidak boleh negatif; jika hasil < 0, dianggap 0)
     *
     * Input:
     * - $usageType          : segmen pembiayaan (enum UsageType).
     * - $calculationPeriod  : periode perhitungan format yyyymm.
     *
     * Ref: PRD Bab 6.1
     *
     * @return array<int, array{
     *   financing_account_id: int,
     *   outstanding_balance: float,
     *   tgkmdl_balance: float|null,
     *   used_ead_value: float,
     *   total_collateral_liquidation_value: float,
     *   selling_cost_rate: float,
     *   selling_cost_amount: float,
     *   ckpn_amount: float,
     *   collectibility: int,
     *   pokpby_code: int,
     * }>
     */
    public function calculatePerAccount(UsageType $usageType, string $calculationPeriod): array
    {
        // Daftar akad eligible dari parameter (kosong = semua akad) — Ref: parameter ckpn_eligible_akad_codes
        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_CKPN, $usageType->value);

        // Ambil akun yang sudah diklasifikasi Individual dari staging — Ref: PRD Bab 6.1
        $stagingAccounts = CkpnPeriodClassification::where('period', $calculationPeriod)
            ->where('classification', ClassificationType::Individual->value)
            ->whereHas('financingAccount', function ($q) use ($usageType, $akadCodes) {
                $q->where('usage_type', $usageType->value)
                    ->when($akadCodes !== null, fn ($w) => $w->whereIn('akad_code', $akadCodes));
            })
            // Akad 03 hanya jika sudah jatuh tempo pada periode perhitungan.
            // maturity_date ada di financing_account_periods, bukan financing_accounts.
            ->whereNot(function ($q) use ($calculationPeriod): void {
                $q->whereHas('financingAccount', fn ($fa) => $fa->where('akad_code', '03'))
                    ->whereRaw("EXISTS (
                        SELECT 1 FROM financing_account_periods
                        WHERE financing_account_id = ckpn_period_classifications.financing_account_id
                        AND period = ?
                        AND (maturity_date IS NULL OR maturity_date > LAST_DAY(STR_TO_DATE(CONCAT(?, '01'), '%Y%m%d')))
                    )", [$calculationPeriod, $calculationPeriod]);
            })
            ->with('financingAccount')
            ->get();

        if ($stagingAccounts->isEmpty()) {
            return [];
        }

        $results = [];

        foreach ($stagingAccounts as $staging) {
            $account = $staging->financingAccount;
            $pokpbyCode = (int) $account->akad_code;

            // Cek apakah akun memenuhi kriteria POKPBY
            if (! PokpbyCriteriaService::meetsCriteria($pokpbyCode, $account->id, $calculationPeriod)) {
                // Skip akun yang tidak memenuhi kriteria (misal: POKPBY=10 belum jatuh tempo)
                continue;
            }

            // Get nilai EAD berdasarkan POKPBY
            $tgkmdl = FinancingAccountPeriod::where('financing_account_id', $account->id)
                ->where('period', $calculationPeriod)
                ->value('tgkmdl');

            $outstanding = (float) $staging->outstanding_balance;
            $eadValue = PokpbyCriteriaService::getEadValue($pokpbyCode, $outstanding, $tgkmdl);

            $totalLiquidationValue = $this->resolveTotalLiquidationValue($account);
            $sellingCostAmount = $totalLiquidationValue * $this->sellingCostRate;

            // Formula PRD Bab 6.1 menggunakan EAD value yang sesuai dengan POKPBY
            $ckpnAmount = max(0.0, $eadValue - $totalLiquidationValue - $sellingCostAmount);

            $results[] = [
                'financing_account_id' => $account->id,
                'outstanding_balance' => $outstanding,
                'tgkmdl_balance' => $tgkmdl,
                'used_ead_value' => $eadValue,
                'total_collateral_liquidation_value' => $totalLiquidationValue,
                'selling_cost_rate' => $this->sellingCostRate,
                'selling_cost_amount' => $sellingCostAmount,
                'ckpn_amount' => $ckpnAmount,
                'collectibility' => (int) $staging->collectibility,
                'pokpby_code' => $pokpbyCode,
            ];
        }

        return $results;
    }

    /**
     * Menghitung total nilai likuidasi jaminan aktif untuk satu akun pembiayaan.
     *
     * Aturan pengambilan nilai per jaminan (prioritas):
     * 1. estimated_sale_value (nilai estimasi penjualan) — jika terisi, digunakan langsung (sudah net biaya).
     * 2. appraisal_value (nilai appraisal) — jika estimated_sale_value NULL, digunakan appraisal_value.
     * 3. Jika keduanya NULL, jaminan tersebut dianggap bernilai 0 (tidak berkontribusi ke total).
     *
     * Hanya jaminan dengan is_active = true yang diperhitungkan.
     * Semua jaminan aktif di-SUM untuk menghasilkan total nilai likuidasi akun.
     *
     * Ref: PRD Bab 6.1
     */
    private function resolveTotalLiquidationValue(FinancingAccount $account): float
    {
        // Gunakan estimated_sale_value; fallback ke appraisal_value jika NULL.
        // Ref: PRD Bab 6.1 — nilai jaminan dari tabel collaterals
        return (float) Collateral::where('financing_account_id', $account->id)
            ->where('is_active', true)
            ->selectRaw('SUM(COALESCE(estimated_sale_value, appraisal_value, 0)) as total')
            ->value('total');
    }
}
