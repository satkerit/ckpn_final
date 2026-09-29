<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Collective;

use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\CkpnIndividualResult;
use App\Models\FinancingAccount;
use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\PdMigrationResult;
use App\Models\PdNetflowResult;
use App\Repositories\AkadCalculationRulesRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CKPN Collective Calculator: PD × LGD × EAD per account.
 *
 * Formula: CKPN_amount = PD_rate × LGD_rate × EAD
 * Where PD & LGD sourced from latest snapshots per account/period/usage_type.
 *
 * Ref: PRD Bab 11 (combination policy configurable, default: netflow PD + ER LGD)
 * ponytail: combination weighting not finalized (PRD Bab 12 open item); hardcoded to default methods.
 * Add when: policy confirmed, create CkpnCombinationPolicy enum/config.
 */
final class CkpnCollectiveCalculator
{
    private string $pdMethod = 'netflow'; // TODO: configurable via PRD Bab 12
    private string $lgdMethod = 'expected_recoveries'; // TODO: configurable via PRD Bab 12

    public function __construct(
        private AkadCalculationRulesRepository $akadRepository,
    ) {}

    /**
     * Calculate CKPN collective per account for single period.
     *
     * @return array{
     *   dimension: array<string, mixed>,
     *   results: array<int, array{
     *     financing_account_id: int,
     *     usage_type: UsageType,
     *     pd_rate: float,
     *     lgd_rate: float,
     *     ead: float,
     *     ckpn_amount: float,
     *     pd_method_used: string,
     *     lgd_method_used: string,
     *     pd_bucket_id: int|null,
     *     pd_quality_grade_id: int|null
     *   }>,
     *   summary: array{total_accounts: int, total_ckpn_amount: float}
     * }
     */
    public function calculate(
        UsageType $usageType,
        string $calculationPeriod,
        ?string $officeCode = null,
        ?string $akadCode = null,
    ): array {
        $results = [];
        $totalCkpnAmount = 0.0;

        // Fetch periods matching calc period & filters, filtered by account usage_type
        $accountIds = DB::table('financing_account_periods')
            ->join('financing_accounts', 'financing_account_periods.financing_account_id', '=', 'financing_accounts.id')
            ->where('financing_account_periods.period', $calculationPeriod)
            ->where('financing_accounts.usage_type', $usageType->value)
            ->when($officeCode, fn ($q) => $q->where('financing_account_periods.office_code', $officeCode))
            ->when($akadCode, fn ($q) => $q->where('financing_account_periods.akad_code', $akadCode))
            ->distinct()
            ->pluck('financing_account_periods.financing_account_id')
            ->values();

        if ($accountIds->isEmpty()) {
            return [
                'dimension' => [
                    'usage_type' => $usageType->value,
                    'calculation_period' => $calculationPeriod,
                    'office_code' => $officeCode,
                    'akad_code' => $akadCode,
                ],
                'results' => [],
                'summary' => [
                    'total_accounts' => 0,
                    'total_ckpn_amount' => 0.0,
                ],
            ];
        }

        // Load accounts + periods in one query
        $accounts = FinancingAccount::whereIn('id', $accountIds)
            ->with(['accountPeriods' => fn ($q) => $q->where('period', $calculationPeriod)])
            ->get();

        \Log::info('CkpnCollectiveCalculator loaded accounts', [
            'account_ids' => $accountIds->toArray(),
            'loaded_count' => $accounts->count(),
        ]);

        foreach ($accounts as $account) {
            $period = $account->accountPeriods->first();
            \Log::info('Processing account', [
                'account_id' => $account->id,
                'account_num' => $account->account_number,
                'periods_count' => $account->accountPeriods->count(),
                'period' => $period?->period,
            ]);
            if (!$period) {
                continue; // Skip if no period data
            }

            $accountOfficeCode = $period->office_code;
            $accountAkadCode = $period->akad_code;

            // Fetch latest PD & LGD snapshots
            $pdResult = $this->fetchLatestPdResult($usageType, $calculationPeriod, $accountOfficeCode, $accountAkadCode);
            $lgdResult = $this->fetchLatestLgdResult($usageType, $calculationPeriod, $accountOfficeCode);

            if (!$pdResult) {
                \Log::info('PD fetch failed', [
                    'usage_type' => $usageType->value,
                    'period' => $calculationPeriod,
                    'office' => $accountOfficeCode,
                    'akad' => $accountAkadCode,
                ]);
                continue;
            }
            if (!$lgdResult) {
                \Log::info('LGD fetch failed', [
                    'usage_type' => $usageType->value,
                    'period' => $calculationPeriod,
                    'office' => $accountOfficeCode,
                ]);
                continue;
            }

            // Get EAD from Individual result (already calculated PD × LGD per account)
            $individualResult = CkpnIndividualResult::where([
                'calculation_period' => $calculationPeriod,
                'usage_type' => $usageType->value,
                'account_number' => $account->account_number,
            ])->first();

            $ead = $individualResult?->outstanding ?? (float) ($period->outstanding_balance ?? 0);

            // Calculate CKPN: PD × LGD × EAD
            $pdRate = (float) $pdResult->pd_rate;
            $lgdRate = (float) $lgdResult->lgd_rate;
            $ckpnAmount = $pdRate * $lgdRate * $ead;

            $results[] = [
                'financing_account_id' => $account->id,
                'usage_type' => $usageType->value,
                'pd_rate' => $pdRate,
                'lgd_rate' => $lgdRate,
                'ead' => $ead,
                'ckpn_amount' => $ckpnAmount,
                'pd_method_used' => $this->pdMethod,
                'lgd_method_used' => $this->lgdMethod,
                'pd_bucket_id' => $pdResult->from_bucket_id ?? null,
                'pd_quality_grade_id' => $pdResult->quality_grade_id ?? null,
            ];

            $totalCkpnAmount += $ckpnAmount;
        }

        return [
            'dimension' => [
                'usage_type' => $usageType->value,
                'calculation_period' => $calculationPeriod,
                'office_code' => $officeCode,
                'akad_code' => $akadCode,
            ],
            'results' => $results,
            'summary' => [
                'total_accounts' => \count($results),
                'total_ckpn_amount' => $totalCkpnAmount,
            ],
        ];
    }

    /**
     * Calculate CKPN collective across multiple segments.
     * Each segment = unique (office_code, akad_code) combination.
     */
    public function calculateDynamic(
        UsageType $usageType,
        string $calculationPeriod,
        array $segmentDimensions = [],
    ): array {
        $segmentResults = [];
        $totalSegments = 0;

        // If no dimensions specified, fallback to single all-account calc
        if (empty($segmentDimensions)) {
            $singleResult = $this->calculate($usageType, $calculationPeriod);
            return [
                'dimensions' => ['usage_type' => $usageType->value],
                'segment_results' => [
                    [
                        'segment' => ['usage_type' => $usageType->value],
                        'results' => $singleResult['results'],
                        'summary' => $singleResult['summary'],
                    ],
                ],
                'total_segments' => 1,
            ];
        }

        // Generate all unique (office_code, akad_code) combinations from account periods
        $segments = $this->extractSegmentCombinations($calculationPeriod, $segmentDimensions);

        foreach ($segments as $segment) {
            $segmentCalc = $this->calculate(
                $usageType,
                $calculationPeriod,
                $segment['office_code'] ?? null,
                $segment['akad_code'] ?? null,
            );

            if (!empty($segmentCalc['results'])) {
                $segmentResults[] = [
                    'segment' => $segment,
                    'results' => $segmentCalc['results'],
                    'summary' => $segmentCalc['summary'],
                ];
                $totalSegments++;
            }
        }

        return [
            'dimensions' => $segmentDimensions,
            'segment_results' => $segmentResults,
            'total_segments' => $totalSegments,
        ];
    }

    /**
     * Fetch latest PD snapshot (PdNetflowResult or PdMigrationResult).
     */
    private function fetchLatestPdResult(
        UsageType $usageType,
        string $calculationPeriod,
        string $officeCode,
        string $akadCode,
    ): ?object {
        if ($this->pdMethod === 'netflow') {
            return PdNetflowResult::where([
                'calculation_period' => $calculationPeriod,
                'usage_type' => $usageType->value,
                'office_code' => $officeCode,
                'akad_code' => $akadCode,
            ])->first();
        }

        if ($this->pdMethod === 'migration') {
            return PdMigrationResult::where([
                'calculation_period' => $calculationPeriod,
                'usage_type' => $usageType->value,
                'office_code' => $officeCode,
                'akad_code' => $akadCode,
            ])->first();
        }

        return null;
    }

    /**
     * Fetch latest LGD snapshot (LgdExpectedRecoveriesResult or LgdCollateralShortfallResult).
     */
    private function fetchLatestLgdResult(
        UsageType $usageType,
        string $calculationPeriod,
        string $officeCode,
    ): ?object {
        if ($this->lgdMethod === 'expected_recoveries') {
            return LgdExpectedRecoveriesResult::where([
                'calculation_period' => $calculationPeriod,
                'usage_type' => $usageType->value,
                'office_code' => $officeCode,
            ])->first();
        }

        if ($this->lgdMethod === 'collateral_shortfall') {
            return LgdCollateralShortfallResult::where([
                'calculation_period' => $calculationPeriod,
                'usage_type' => $usageType->value,
                'office_code' => $officeCode,
            ])->first();
        }

        return null;
    }

    /**
     * Extract unique (office_code, akad_code) combinations from account periods for given calculation period.
     */
    private function extractSegmentCombinations(
        string $calculationPeriod,
        array $segmentDimensions,
    ): array {
        $query = DB::table('financing_account_periods')
            ->where('period', $calculationPeriod)
            ->distinct();

        $results = [];
        $seen = [];

        if (\in_array('office_code', $segmentDimensions, true)) {
            $query->select('office_code', 'akad_code');
        } elseif (\in_array('akad_code', $segmentDimensions, true)) {
            $query->select('akad_code');
        } else {
            $query->select(DB::raw("'all' as segment_key"));
        }

        foreach ($query->get() as $row) {
            $key = json_encode($row);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $results[] = (array) $row;
            }
        }

        return $results;
    }
}
