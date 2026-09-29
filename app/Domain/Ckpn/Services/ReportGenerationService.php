<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\PdMigrationResult;
use App\Models\PdNetflowResult;
use Illuminate\Support\Collection;

class ReportGenerationService
{
    public function generateRekapitulasi(
        string $period,
        UsageType $usageType,
    ): array {
        return [
            'period' => $period,
            'usage_type' => $usageType->label(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'summary' => $this->generateSummary($period, $usageType),
            'pd_netflow' => $this->getPdNetflowSummary($period, $usageType),
            'pd_migration' => $this->getPdMigrationSummary($period, $usageType),
            'lgd_er' => $this->getLgdErSummary($period, $usageType),
            'lgd_cs' => $this->getLgdCsSummary($period, $usageType),
            'ckpn_individual' => $this->getCkpnIndividualSummary($period, $usageType),
            'ckpn_collective' => $this->getCkpnCollectiveSummary($period, $usageType),
        ];
    }

    private function generateSummary(string $period, UsageType $usageType): array
    {
        $runLogs = CalculationRunLog::query()
            ->where('period', $period)
            ->where('usage_type', $usageType)
            ->where('status', RunStatus::Approved)
            ->get();

        return [
            'total_runs' => $runLogs->count(),
            'approved_runs' => $runLogs->where('status', RunStatus::Approved)->count(),
            'total_run_ids' => $runLogs->pluck('id')->toArray(),
        ];
    }

    private function getPdNetflowSummary(string $period, UsageType $usageType): array
    {
        $results = PdNetflowResult::query()
            ->where('calculation_period', $period)
            ->where('usage_type', $usageType)
            ->get();

        return [
            'total_records' => $results->count(),
            'min_pd_rate' => $results->min('pd_rate'),
            'max_pd_rate' => $results->max('pd_rate'),
            'avg_pd_rate' => $results->avg('pd_rate'),
            'segments' => $results->groupBy('office_code')->map(fn ($g) => [
                'office_code' => $g->first()->office_code,
                'count' => $g->count(),
                'avg_pd_rate' => $g->avg('pd_rate'),
            ])->values()->toArray(),
        ];
    }

    private function getPdMigrationSummary(string $period, UsageType $usageType): array
    {
        $results = PdMigrationResult::query()
            ->where('calculation_period', $period)
            ->where('usage_type', $usageType)
            ->get();

        return [
            'total_records' => $results->count(),
            'min_pd_rate' => $results->min('pd_rate'),
            'max_pd_rate' => $results->max('pd_rate'),
            'avg_pd_rate' => $results->avg('pd_rate'),
        ];
    }

    private function getLgdErSummary(string $period, UsageType $usageType): array
    {
        $results = LgdExpectedRecoveriesResult::query()
            ->where('calculation_period', $period)
            ->where('usage_type', $usageType)
            ->get();

        return [
            'total_records' => $results->count(),
            'min_lgd_rate' => $results->min('lgd_rate'),
            'max_lgd_rate' => $results->max('lgd_rate'),
            'avg_lgd_rate' => $results->avg('lgd_rate'),
            'total_writeoff' => $results->sum('total_writeoff_amount'),
            'total_recovery' => $results->sum('total_recovery_amount'),
        ];
    }

    private function getLgdCsSummary(string $period, UsageType $usageType): array
    {
        $results = LgdCollateralShortfallResult::query()
            ->where('calculation_period', $period)
            ->where('usage_type', $usageType)
            ->get();

        return [
            'total_records' => $results->count(),
            'min_lgd_rate' => $results->min('lgd_rate'),
            'max_lgd_rate' => $results->max('lgd_rate'),
            'avg_lgd_rate' => $results->avg('lgd_rate'),
            'total_outstanding' => $results->sum('total_outstanding'),
            'total_shortfall' => $results->sum('total_shortfall'),
        ];
    }

    private function getCkpnIndividualSummary(string $period, UsageType $usageType): array
    {
        $results = CkpnIndividualResult::query()
            ->where('calculation_period', $period)
            ->where('usage_type', $usageType)
            ->get();

        return [
            'total_accounts' => $results->count(),
            'min_ckpn_rate' => $results->min('ckpn_rate'),
            'max_ckpn_rate' => $results->max('ckpn_rate'),
            'avg_ckpn_rate' => $results->avg('ckpn_rate'),
            'total_ckpn_amount' => $results->sum('ckpn_amount'),
            'total_ead' => $results->sum('ead'),
        ];
    }

    private function getCkpnCollectiveSummary(string $period, UsageType $usageType): array
    {
        $results = CkpnCollectiveResult::query()
            ->where('calculation_period', $period)
            ->where('usage_type', $usageType)
            ->get();

        return [
            'total_accounts' => $results->count(),
            'min_ckpn_rate' => $results->min('ckpn_rate'),
            'max_ckpn_rate' => $results->max('ckpn_rate'),
            'avg_ckpn_rate' => $results->avg('ckpn_rate'),
            'total_ckpn_amount' => $results->sum('ckpn_amount'),
            'segments_count' => $results->distinct('office_code')->count(),
        ];
    }
}
