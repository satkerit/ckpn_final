<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Individual\CkpnIndividualCalculator;
use App\Domain\Ckpn\Services\SnapshotWriter;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\CkpnIndividualResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued job untuk menjalankan perhitungan CKPN Individual — PRD Bab 6.
 * Posisi dalam alur sistem: LANGKAH 3 (setelah PD & LGD selesai).
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
    ) {}

    public function handle(): void
    {
        $runLog = CalculationRunLog::findOrFail($this->runLogId);
        $usageType = UsageType::from($this->usageType);

        // Idempotency guard
        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $calculator = app(CkpnIndividualCalculator::class);
            $result = $calculator->calculate($usageType, $this->calculationPeriod);

            // Write per-account results to snapshot
            foreach ($result['accounts'] as $accountData) {
                CkpnIndividualResult::create([
                    'calculation_run_log_id' => $runLog->id,
                    'account_number' => $accountData['account_number'],
                    'usage_type' => $usageType,
                    'office_code' => $accountData['office_code'],
                    'akad_code' => $accountData['akad_code'],
                    'calculation_period' => $this->calculationPeriod,
                    'bucket' => $accountData['bucket'],
                    'days_past_due' => $accountData['days_past_due'],
                    'pd_rate' => $accountData['pd_rate'],
                    'lgd_rate' => $accountData['lgd_rate'],
                    'ckpn_rate' => $accountData['ckpn_rate'],
                    'outstanding' => $accountData['outstanding'],
                    'ckpn_amount' => $accountData['ckpn_amount'],
                ]);
            }

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update(['status' => RunStatus::Failed, 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        }
    }
}
