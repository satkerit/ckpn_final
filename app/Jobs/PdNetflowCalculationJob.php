<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Pd\Netflow\BucketMovementValidator;
use App\Domain\Ckpn\Pd\Netflow\PdNetflowCalculator;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\PeriodHelper;
use App\Domain\Ckpn\Services\RollingWindowResolver;
use App\Domain\Ckpn\Services\SnapshotWriter;
use App\Enums\CalculationMethodKey;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationDataRange;
use App\Models\CalculationRunLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued job untuk menjalankan perhitungan PD Netflow per segmen per periode.
 *
 * PD Netflow = probabilitas default berbasis pergerakan outstanding antar bucket tunggakan.
 * Job ini adalah LANGKAH 1 alur kalkulasi CKPN — harus selesai sebelum CkpnCollectiveCalculationJob
 * (jika metode PD yang dipilih = Netflow).
 *
 * Prasyarat:
 *   - financing_account_periods sudah terisi untuk periode calculationPeriod (PopulatePeriodDebtorsJob)
 *   - Tabel calculation_parameters memiliki konfigurasi:
 *       pd_netflow_rolling_window_months    (default 36 — panjang window historis)
 *       pd_netflow_forward_projection_months (default 6 — bulan proyeksi ke depan)
 *
 * Idempotency: skip jika RunStatus = Completed atau Approved (tidak akan dihitung ulang).
 *
 * Output snapshot (3 tabel):
 *   1. pd_netflow_results        — PD rate per bucket, ringkasan per segmen+periode
 *   2. pd_netflow_details        — transition rate, compound rate, outstanding per bucket per periode
 *   3. pd_netflow_histories      — rekam jejak JSON kalkulasi per calculationPeriod+usageType+runLog
 *
 * Ref: PRD Bab 7, AGENTS.md §4 (idempotent)
 */
class PdNetflowCalculationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $runLogId,
        private readonly int $usageType,
        private readonly string $calculationPeriod,
    ) {}

    /**
     * Jalankan perhitungan PD Netflow untuk satu segmen dan satu periode kalkulasi.
     *
     * Langkah eksekusi:
     *   1. Idempotency check — skip jika status Completed/Approved (data tidak akan ditimpa)
     *   2. Set status → Processing dan catat started_at
     *   3. Baca parameter dari calculation_parameters:
     *      - pd_netflow_rolling_window_months    (default 36) — panjang window observasi
     *      - pd_netflow_forward_projection_months (default 6) — bulan proyeksi ke depan
     *   4. Jalankan PdNetflowCalculator::calculate() → hasilkan PD rate per bucket
     *      (transition_rates, compound_rates, outstanding_map, projected_rates)
     *   5. Jalankan BucketMovementValidator untuk flag anomali data quality
     *   6. Simpan 3 snapshot via SnapshotWriter:
     *      - writePdNetflowResult()  → ringkasan PD per segmen (pd_netflow_results)
     *      - writePdNetflowDetail()  → detail per bucket per periode (pd_netflow_details)
     *      - writePdNetflowHistory() → rekam jejak JSON (pd_netflow_histories)
     *   7. Set status → Completed; jika exception → set Failed + simpan error_message
     *
     * Sumber data utama: financing_account_periods (period range = window months sebelum calculationPeriod)
     * Parameter diambil dengan priority: segmen spesifik (usage_type match) > all-account (usage_type IS NULL)
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
            // Ambil window parameter dari calculation_data_ranges (Ref: AGENTS.md §9 — jangan hardcode)
            $windowMonths = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::PdNetflow,
                'pd_netflow_rolling_window_months',
                usageType: (int) $this->usageType,
                default: 36,
            );

            $forwardMonths = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::PdNetflow,
                'pd_netflow_forward_projection_months',
                usageType: (int) $this->usageType,
                default: 6,
            );

            $resolver = new RollingWindowResolver($windowMonths, $forwardMonths);
            $validator = new BucketMovementValidator;
            $calculator = new PdNetflowCalculator($resolver, $validator);
            $writer = new SnapshotWriter;

            $result = $calculator->calculate($usageType, $this->calculationPeriod);

            // Catatan dasar data perhitungan — Ref: instruksi user (notes per baris hasil)
            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $this->usageType);
            $notes = sprintf(
                "Dasar data PD Netflow [%s]:\n"
                    ."- Sumber: financing_account_periods via PdNetflowBaseline\n"
                    ."- Filter: financing_status='A' ATAU (writeoff_status='W' DAN writeoff_date=periode yyyymm); akad eligible: %s; akad 03 hanya jika jatuh tempo (maturity<=LAST_DAY(periode))\n"
                    ."- Window: rolling_window=%d bln, forward_projection=%d bln\n"
                    ."- Periode data rate: %s s.d. %s (aktual) + proyeksi s.d. %s\n"
                    ."- Bucketing: tgkhari -> bucket B1..B14\n"
                    .'- Jumlah baris historis dipakai: %d',
                $usageType->label(),
                AkadEligibilityService::formatCodes($akadCodes),
                $windowMonths,
                $forwardMonths,
                $resolver->rateStartPeriod($this->calculationPeriod),
                $this->calculationPeriod,
                $result['compound_end'],
                count($result['history']),
            );

            $writer->writePdNetflowResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                dataStart: $resolver->rateStartPeriod($this->calculationPeriod),
                dataEnd: $this->calculationPeriod, // periode penilaian = akhir data, bukan akhir rantai compound
                windowMonths: $windowMonths,
                pdRates: $result['pd_rates'],
                notes: $notes,
            );

            $writer->writePdNetflowDetail(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                transitionRates: $result['transition_rates'],
                compoundRates: $result['compound_rates'],
                outstandingMap: $result['outstanding_map'],
                projPeriods: $result['proj_periods'],
                rateStart: $result['rate_start'],
                compoundEnd: $result['compound_end'],
            );

            $writer->writePdNetflowHistory(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                history: $result['history'],
            );

            // Simpan ke 4 tabel tersegmentasi (Konsolidasi + segmen spesifik) — Ref: PRD Bab 7
            $lastPeriod = $this->calculationPeriod;
            $prevPeriod = PeriodHelper::shiftBack($lastPeriod, 1);
            // Ambil transition_rate terakhir per bucket (periode calculationPeriod)
            $flatTransition = [];
            foreach ($result['transition_rates'] as $bucketId => $periods) {
                $flatTransition[$bucketId] = (float) ($periods[$lastPeriod] ?? end($periods) ?: 0.0);
            }
            // Ambil compound_rate per bucket pada periode kalkulasi (sama dengan transition_rate)
            $flatCompound = [];
            foreach ($result['compound_rates'] as $bucketId => $periods) {
                $flatCompound[$bucketId] = (float) ($periods[$lastPeriod] ?? end($periods) ?: 0.0);
            }
            $sourceOs = [];
            $destOs = [];
            foreach ($result['pd_rates'] as $bucketId => $_) {
                $sourceOs[$bucketId] = (float) ($result['outstanding_map'][$prevPeriod][$bucketId] ?? 0.0);
                $destOs[$bucketId] = (float) ($result['outstanding_map'][$lastPeriod][$bucketId] ?? 0.0);
            }

            $writer->writePdNetflowSegmented(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                dataStart: $resolver->rateStartPeriod($this->calculationPeriod),
                dataEnd: $this->calculationPeriod,
                windowMonths: $windowMonths,
                pdRates: $result['pd_rates'],
                transitionRates: $flatTransition,
                compoundRates: $flatCompound,
                sourceOs: $sourceOs,
                destOs: $destOs,
            );

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }
}
