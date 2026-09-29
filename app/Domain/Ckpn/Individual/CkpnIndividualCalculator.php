<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Individual;

use App\Domain\Ckpn\Services\BucketingService;
use App\Enums\UsageType;
use Illuminate\Support\Facades\DB;

/**
 * CKPN Individual calculation — PRD Bab 6.
 *
 * Formula:
 *   CKPN_Individual = PD_Individual (dari bucket) × LGD (dari eligible criteria)
 *
 * Alur:
 * 1. Muat akun consumer/korporat dari periode perhitungan
 * 2. Untuk setiap akun: tentukan bucket dari hari tunggakan (via BucketingService)
 * 3. Ambil PD rate untuk bucket tersebut (dari pd_rates atau mapping tabel)
 * 4. Ambil LGD rate sesuai akad (dari calculation_parameters atau LGD snapshot terkini)
 * 5. Hitung: CKPN_Individual = PD × LGD
 * 6. Simpan hasil per akun ke tabel ckpn_individual_results (insert-only)
 *
 * Parameter konfigurasi (dari calculation_parameters):
 *   - ckpn_individual_use_all_account: jika true, semua akun; jika false (default), hanya sesuai usage_type
 *   - ckpn_individual_lgd_source: 'snapshot' (default) atau 'parameter' (fallback ke parameter tabel)
 */
final class CkpnIndividualCalculator
{
    public function __construct(
        private readonly BucketingService $bucketingService,
    ) {}

    /**
     * Hitung CKPN Individual untuk satu usage_type dan periode.
     * Return detail per-akun untuk disimpan ke snapshot.
     *
     * @param  string  $calculationPeriod  Format yyyymm (mis. 202409)
     * @return array{
     *   account_count: int,
     *   total_ckpn: float,
     *   ckpn_avg: float,
     *   ckpn_min: float,
     *   ckpn_max: float,
     *   accounts: array<int, array{account_number: string, akad_code: string, office_code: string, days_past_due: int, bucket: int, pd_rate: float, lgd_rate: float, ckpn_rate: float, outstanding: float, ckpn_amount: float}>
     * }
     */
    public function calculate(UsageType $usageType, string $calculationPeriod): array
    {
        $accounts = $this->loadAccounts($usageType, $calculationPeriod);

        if ($accounts->isEmpty()) {
            return [
                'account_count' => 0,
                'total_ckpn' => 0.0,
                'ckpn_avg' => 0.0,
                'ckpn_min' => 0.0,
                'ckpn_max' => 0.0,
                'accounts' => [],
            ];
        }

        $ckpnValues = [];
        $accountDetails = [];

        foreach ($accounts as $account) {
            $bucket = $this->bucketingService->resolveBucketId($account->days_past_due ?? 0);
            if ($bucket === null) {
                continue; // Skip if bucket not resolved
            }
            $pdRate = $this->getPdRate($bucket, $usageType);
            $lgdRate = $this->getLgdRate($account->akad_code, $usageType);

            $ckpnRate = $pdRate * $lgdRate;
            $ckpnAmount = $ckpnRate * $account->outstanding;
            
            $ckpnValues[] = $ckpnRate;
            $accountDetails[] = [
                'account_number' => $account->account_number,
                'akad_code' => $account->akad_code,
                'office_code' => $account->office_code,
                'days_past_due' => $account->days_past_due,
                'bucket' => $bucket,
                'pd_rate' => $pdRate,
                'lgd_rate' => $lgdRate,
                'ckpn_rate' => $ckpnRate,
                'outstanding' => $account->outstanding,
                'ckpn_amount' => $ckpnAmount,
            ];
        }

        return [
            'account_count' => count($ckpnValues),
            'total_ckpn' => array_sum($ckpnValues),
            'ckpn_avg' => count($ckpnValues) > 0 ? array_sum($ckpnValues) / count($ckpnValues) : 0.0,
            'ckpn_min' => count($ckpnValues) > 0 ? min($ckpnValues) : 0.0,
            'ckpn_max' => count($ckpnValues) > 0 ? max($ckpnValues) : 0.0,
            'accounts' => $accountDetails,
        ];
    }

    private function loadAccounts($usageType, string $calculationPeriod)
    {
        return DB::table('financing_account_periods as fap')
            ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
            ->where('fap.period', $calculationPeriod)
            ->where('fa.usage_type', $usageType->value)
            ->where('fa.is_active', true)
            ->where('fap.outstanding_balance', '>', 0)
            ->select([
                'fa.account_number',
                'fa.akad_code',
                'fa.office_code',
                'fap.tgkhari as days_past_due',
                'fap.outstanding_balance as outstanding',
            ])
            ->get()
            ->map(fn ($row) => (object) [
                'account_number' => $row->account_number,
                'akad_code' => $row->akad_code,
                'office_code' => $row->office_code,
                'days_past_due' => (int) ($row->days_past_due ?? 0),
                'outstanding' => (float) $row->outstanding,
            ]);
    }

    private function getPdRate(int $bucket, UsageType $usageType): float
    {
        $rate = DB::table('pd_netflow_result')
            ->where('from_bucket_id', $bucket)
            ->where('usage_type', $usageType->value)
            ->latest('id')
            ->value('pd_rate');

        return $rate ? (float) $rate : 0.5;
    }

    private function getLgdRate(?string $akadCode, UsageType $usageType): float
    {
        $rate = DB::table('lgd_expected_recoveries_result')
            ->where('usage_type', $usageType->value)
            ->where('is_all_account', false)
            ->latest('id')
            ->value('lgd_rate');

        return $rate ? (float) $rate : 0.5;
    }
}
