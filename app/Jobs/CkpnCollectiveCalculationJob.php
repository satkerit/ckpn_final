<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Collective\CkpnCollectiveCalculator;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\CkpnCollectiveResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job: Calculate CKPN Collective (PD × LGD × EAD per account).
 *
 * Idempotent: skips if run_log status is Completed or Approved.
 * Writes per-account snapshots to ckpn_collective_result (insert-only).
 *
 * Ref: PRD Bab 11, 16
 */
final class CkpnCollectiveCalculationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour

    public function __construct(
        private int $calculationRunLogId,
        private string $calculationPeriod,
        private UsageType $usageType,
        private array $segmentDimensions = [],
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $runLog = CalculationRunLog::findOrFail($this->calculationRunLogId);

        // Idempotency: skip if already Completed or Approved
        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        try {
            $runLog->update(['status' => RunStatus::Processing]);

            $calculator = app(CkpnCollectiveCalculator::class);

            // Branch: dynamic vs legacy
            if (!empty($this->segmentDimensions)) {
                $result = $calculator->calculateDynamic(
                    $this->usageType,
                    $this->calculationPeriod,
                    $this->segmentDimensions,
                );

                foreach ($result['segment_results'] as $segmentResult) {
                    $this->writeSegmentResult($runLog, $segmentResult);
                }
            } else {
                // Legacy: single all-account calculation
                $result = $calculator->calculate(
                    $this->usageType,
                    $this->calculationPeriod,
                );

                $this->writeSegmentResult($runLog, [
                    'segment' => [
                        'usage_type' => $this->usageType->value,
                        'calculation_period' => $this->calculationPeriod,
                    ],
                    'results' => $result['results'],
                    'summary' => $result['summary'],
                ]);
            }

            $runLog->update([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Write segment results to ckpn_collective_result table (insert-only).
     */
    private function writeSegmentResult(CalculationRunLog $runLog, array $segmentResult): void
    {
        $segment = $segmentResult['segment'];
        $results = $segmentResult['results'];

        $officeCode = $segment['office_code'] ?? null;
        $akadCode = $segment['akad_code'] ?? null;

        foreach ($results as $result) {
            CkpnCollectiveResult::create([
                'calculation_run_log_id' => $runLog->id,
                'financing_account_id' => $result['financing_account_id'],
                'usage_type' => $result['usage_type'],
                'office_code' => $officeCode,
                'calculation_period' => $this->calculationPeriod,
                'pd_method_used' => $result['pd_method_used'],
                'pd_rate' => $result['pd_rate'],
                'lgd_method_used' => $result['lgd_method_used'],
                'lgd_rate' => $result['lgd_rate'],
                'ead' => $result['ead'],
                'pd_bucket_id' => $result['pd_bucket_id'],
                'pd_quality_grade_id' => $result['pd_quality_grade_id'],
                'ckpn_amount' => $result['ckpn_amount'],
            ]);
        }
    }
}
