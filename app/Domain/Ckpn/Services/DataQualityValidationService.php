<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\UsageType;
use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Support\Collection;

class DataQualityValidationService
{
    /**
     * Validate LGD Expected Recoveries snapshot — check for anomalies.
     *
     * @return array<string, array> Anomalies found (empty if none)
     */
    public function validateLgdEr(LgdExpectedRecoveriesResult $snapshot): array
    {
        $anomalies = [];

        // Check 1: LGD rate out of bounds [0, 1]
        if ((float) $snapshot->lgd_rate < 0 || (float) $snapshot->lgd_rate > 1) {
            $anomalies['lgd_rate_out_of_bounds'] = [
                'severity' => 'critical',
                'message' => sprintf(
                    'LGD rate %.8f outside valid range [0.00, 1.00]',
                    (float) $snapshot->lgd_rate
                ),
            ];
        }

        // Check 2: Recovery rate out of bounds
        if ((float) $snapshot->expected_recovery_rate < 0 || (float) $snapshot->expected_recovery_rate > 1) {
            $anomalies['recovery_rate_out_of_bounds'] = [
                'severity' => 'critical',
                'message' => sprintf(
                    'Recovery rate %.8f outside valid range [0.00, 1.00]',
                    (float) $snapshot->expected_recovery_rate
                ),
            ];
        }

        // Check 3: LGD + Recovery Rate should ≈ 1.0
        $sum = (float) $snapshot->lgd_rate + (float) $snapshot->expected_recovery_rate;
        if (abs($sum - 1.0) > 0.0001) {
            $anomalies['lgd_recovery_mismatch'] = [
                'severity' => 'warning',
                'message' => sprintf(
                    'LGD rate + Recovery rate = %.8f, expected ≈ 1.00',
                    $sum
                ),
            ];
        }

        // Check 4: Total writeoff & recovery consistency
        if ((float) $snapshot->total_writeoff_amount <= 0) {
            $anomalies['zero_writeoff'] = [
                'severity' => 'info',
                'message' => 'Total writeoff = 0 (no write-off data in window)',
            ];
        }

        if ((float) $snapshot->total_recovery_amount < 0) {
            $anomalies['negative_recovery'] = [
                'severity' => 'critical',
                'message' => sprintf(
                    'Total recovery %.2f is negative',
                    (float) $snapshot->total_recovery_amount
                ),
            ];
        }

        if ((float) $snapshot->total_recovery_amount > (float) $snapshot->total_writeoff_amount) {
            $anomalies['recovery_exceeds_writeoff'] = [
                'severity' => 'warning',
                'message' => sprintf(
                    'Total recovery (%.2f) exceeds total writeoff (%.2f)',
                    (float) $snapshot->total_recovery_amount,
                    (float) $snapshot->total_writeoff_amount
                ),
            ];
        }

        // Check 5: All-account fallback flag
        if ($snapshot->is_all_account) {
            $anomalies['fallback_all_account'] = [
                'severity' => 'warning',
                'message' => 'All-account fallback active (segment was empty)',
            ];
        }

        return $anomalies;
    }

    /**
     * Validate LGD Collateral Shortfall snapshot — check for anomalies.
     *
     * @return array<string, array> Anomalies found (empty if none)
     */
    public function validateLgdCs(LgdCollateralShortfallResult $snapshot): array
    {
        $anomalies = [];

        // Check 1: LGD rate out of bounds
        if ((float) $snapshot->lgd_rate < 0 || (float) $snapshot->lgd_rate > 1) {
            $anomalies['lgd_rate_out_of_bounds'] = [
                'severity' => 'critical',
                'message' => sprintf(
                    'LGD rate %.8f outside valid range [0.00, 1.00]',
                    (float) $snapshot->lgd_rate
                ),
            ];
        }

        // Check 2: Account count
        if ($snapshot->account_count <= 0) {
            $anomalies['zero_accounts'] = [
                'severity' => 'warning',
                'message' => 'Zero eligible accounts (no collateral data)',
            ];
        }

        // Check 3: Outstanding & shortfall consistency
        if ((float) $snapshot->total_outstanding <= 0) {
            $anomalies['zero_outstanding'] = [
                'severity' => 'warning',
                'message' => 'Total outstanding = 0',
            ];
        }

        if ((float) $snapshot->total_shortfall < 0) {
            $anomalies['negative_shortfall'] = [
                'severity' => 'critical',
                'message' => sprintf(
                    'Total shortfall %.2f is negative',
                    (float) $snapshot->total_shortfall
                ),
            ];
        }

        if ((float) $snapshot->total_shortfall > (float) $snapshot->total_outstanding) {
            $anomalies['shortfall_exceeds_outstanding'] = [
                'severity' => 'warning',
                'message' => sprintf(
                    'Shortfall (%.2f) exceeds outstanding (%.2f) — possible data issue',
                    (float) $snapshot->total_shortfall,
                    (float) $snapshot->total_outstanding
                ),
            ];
        }

        // Check 4: Collateral value consistency
        if ((float) $snapshot->total_collateral_value < 0) {
            $anomalies['negative_collateral'] = [
                'severity' => 'critical',
                'message' => sprintf(
                    'Total collateral value %.2f is negative',
                    (float) $snapshot->total_collateral_value
                ),
            ];
        }

        // Check 5: LGD = Shortfall / Outstanding (if outstanding > 0)
        if ((float) $snapshot->total_outstanding > 0) {
            $calculatedLgd = (float) $snapshot->total_shortfall / (float) $snapshot->total_outstanding;
            if (abs($calculatedLgd - (float) $snapshot->lgd_rate) > 0.0001) {
                $anomalies['lgd_calculation_mismatch'] = [
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Calculated LGD (%.8f) differs from stored (%.8f)',
                        $calculatedLgd,
                        (float) $snapshot->lgd_rate
                    ),
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Get all validation results for a period — aggregate both LGD-ER & LGD-CS.
     *
     * @return array<string, mixed>
     */
    public function getPeriodValidationSummary(string $period, UsageType $usageType): array
    {
        $erResults = LgdExpectedRecoveriesResult::where('calculation_period', $period)
            ->where('usage_type', $usageType->value)
            ->get();

        $csResults = LgdCollateralShortfallResult::where('calculation_period', $period)
            ->where('usage_type', $usageType->value)
            ->get();

        $erAnomalies = $erResults->map(fn ($r) => [
            'type' => 'lgd_er',
            'snapshot_id' => $r->id,
            'office_code' => $r->office_code,
            'anomalies' => $this->validateLgdEr($r),
        ])->filter(fn ($item) => ! empty($item['anomalies']))->values();

        $csAnomalies = $csResults->map(fn ($r) => [
            'type' => 'lgd_cs',
            'snapshot_id' => $r->id,
            'office_code' => $r->office_code,
            'anomalies' => $this->validateLgdCs($r),
        ])->filter(fn ($item) => ! empty($item['anomalies']))->values();

        $allAnomalies = $erAnomalies->merge($csAnomalies);

        return [
            'period' => $period,
            'usage_type' => $usageType->label(),
            'total_snapshots' => $erResults->count() + $csResults->count(),
            'total_anomalies' => $allAnomalies->count(),
            'critical_count' => $allAnomalies->sum(fn ($item) => collect($item['anomalies'])->where('severity', 'critical')->count()),
            'warning_count' => $allAnomalies->sum(fn ($item) => collect($item['anomalies'])->where('severity', 'warning')->count()),
            'info_count' => $allAnomalies->sum(fn ($item) => collect($item['anomalies'])->where('severity', 'info')->count()),
            'anomalies' => $allAnomalies,
        ];
    }
}
