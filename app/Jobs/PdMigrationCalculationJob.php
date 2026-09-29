<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Pd\Migration\MigrationMatrixBuilder;
use App\Domain\Ckpn\Pd\Migration\PdMigrationCalculator;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\PeriodHelper;
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
 * Queued job untuk menjalankan perhitungan PD Migration per segmen per periode.
 *
 * PD Migration = probabilitas default berbasis matriks migrasi kualitas pembiayaan antar cohort triwulanan.
 * Metode alternatif terhadap PD Netflow — keduanya bisa diaktifkan, namun hanya satu yang dipakai
 * sebagai dasar CKPN Kolektif (ditentukan parameter 'pd_calculation_method').
 *
 * Prasyarat:
 *   - financing_account_periods sudah terisi untuk periode target dan window tahun sebelumnya
 *   - quality_grades (kolektibilitas 1–5) sudah terdefinisi di tabel master
 *   - Tabel calculation_parameters memiliki konfigurasi:
 *       pd_migration_matrix_count (default 4 — jumlah matriks migrasi, Ref: pd-migration.md Bab 2)
 *
 * Idempotency: skip jika RunStatus = Completed atau Approved.
 *
 * Output snapshot (1 tabel):
 *   pd_migration_results — PD rate per quality_grade per segmen per periode
 *   Format pdRates: array[quality_grade_id] = pd_rate  (misal [1 => 0.02, 2 => 0.05, ...])
 *
 * Cohort: setiap triwulan dalam window → matriks migrasi → rata-rata antar cohort = PD final.
 * Ref: PRD Bab 8, AGENTS.md §4 (idempotent)
 */
class PdMigrationCalculationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $runLogId,
        private readonly int $usageType,
        private readonly string $calculationPeriod,
        /** NULL = konsolidasi semua kantor; 'xxx' = pecahan per kode kantor (level 1 segmentasi) */
        private readonly ?string $officeCode = null,
        /** NULL = konsolidasi semua akad; 'xx' = pecahan per kode akad (level 2 segmentasi) */
        private readonly ?string $akadCode = null,
        /** Dimensi dinamis untuk segmentasi (mis. ['office_code', 'akad_code']). Empty = legacy single calculate() */
        private readonly array $segmentDimensions = [],
    ) {}

    /**
     * Jalankan perhitungan PD Migration untuk satu segmen dan satu periode kalkulasi.
     *
     * Langkah eksekusi:
     *   1. Idempotency check — skip jika status Completed/Approved
     *   2. Set status → Processing dan catat started_at
     *   3. Baca parameter 'pd_migration_rolling_window_years' (default 3) dari calculation_parameters
     *   4. Buat cohort triwulanan dari data financing_account_periods dalam window:
     *      - Setiap cohort = subset akun yang masuk pada triwulan tsb (MigrationMatrixBuilder)
     *      - Setiap cohort menghasilkan matriks migrasi (quality_grade awal → quality_grade akhir)
     *   5. Hitung PD per quality_grade = rata-rata proporsi migrasi ke kolektibilitas lebih buruk
     *      antar cohort selama window (PdMigrationCalculator::calculate())
     *   6. Simpan snapshot via SnapshotWriter::writePdMigrationResult()
     *      → pd_migration_results: PD rate per quality_grade per segmen per periode
     *   7. Set status → Completed; jika exception → set Failed + simpan error_message
     *
     * Cara baca output pdRates: array[quality_grade_id] = pd_rate
     *   Contoh: [1 => 0.00, 2 => 0.03, 3 => 0.12, 4 => 0.35, 5 => 1.00]
     *   quality_grade_id 1 = kol.1 (Lancar), 5 = kol.5 (WO)
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
            $builder = new MigrationMatrixBuilder;
            $calculator = new PdMigrationCalculator($builder);
            $writer = new SnapshotWriter;

            // Cabang: calculateDynamic() jika segmentDimensions disediakan, else single calculate()
            if (!empty($this->segmentDimensions)) {
                $dynamicResult = $calculator->calculateDynamic($usageType, $this->calculationPeriod, $this->segmentDimensions);
                foreach ($dynamicResult['segment_results'] as $segmentData) {
                    $result = $segmentData['result'];
                    $this->writeSegmentResult(
                        $writer,
                        $runLog,
                        $usageType,
                        $segmentData['segment'],
                    );
                }
                $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
                return;
            }

            // Ambil lookback_months (default 12), hitung matrix_count dinamis (Ref: IMPROVEMENT_PLAN_PD_CALCULATION Phase 2)
            $lookbackMonths = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::PdMigration,
                'pd_migration_lookback_months',
                officeCode: $this->officeCode,
                usageType: (int) $this->usageType,
                akadCode: $this->akadCode,
                default: 12,
            );

            $matrixCount = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::PdMigration,
                'pd_migration_matrix_count',
                officeCode: $this->officeCode,
                usageType: (int) $this->usageType,
                akadCode: $this->akadCode,
                default: null,
            ) ?? 12;

            $pdRates = $calculator->calculate($usageType, $this->calculationPeriod, $this->officeCode, $this->akadCode);

            // Resolve data range untuk metadata (Ref: pd-migration.md Bab 1-2)
            // Anchor quarter T diturunkan dari calculationPeriod; matriks ke-(N-1) = T - 3(N-1) bulan,
            // dataStart = matriks ter-awal - 12 bulan.
            $anchor = PeriodHelper::anchorQuarter($this->calculationPeriod);
            $earliestEnd = PeriodHelper::shiftBack($anchor, ($matrixCount - 1) * 3);
            $dataStart = PeriodHelper::shiftBack($earliestEnd, 12);
            $dataEnd = $this->calculationPeriod;
            $cohortCount = $matrixCount;

            // Catatan dasar data perhitungan — Ref: instruksi user (notes per baris hasil)
            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $this->usageType);
            $notes = sprintf(
                "Dasar data PD Migration [%s]:\n"
                    ."- Sumber: financing_outstanding_quarterly (posisi awal & akhir matriks) + financing_account_periods (writeoff)\n"
                    ."- Filter: akad eligible: %s; akad 03 hanya jika JTP saat writeoff (maturity<=writeoff_date)\n"
                    ."- Anchor Quarter (T): %s; jumlah matriks migrasi = %d (M1=T, Mk = T - 3(k-1) bln), masing-masing dilacak 12 bln\n"
                    ."- Data range: %s s.d. %s\n"
                    .'- matrix_count=%d',
                $usageType->label(),
                AkadEligibilityService::formatCodes($akadCodes),
                $anchor,
                $matrixCount,
                $dataStart,
                $dataEnd,
                $matrixCount,
            );

            $writer->writePdMigrationResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                dataStart: $dataStart,
                dataEnd: $dataEnd,
                cohortCount: $cohortCount,
                pdRates: $pdRates,
                notes: $notes,
                officeCode: $this->officeCode,
                akadCode: $this->akadCode,
            );

            // Simpan detail matriks migrasi per cohort (satu baris per from_grade → to_grade/WO).
            // Ref: pd-migration.md Bab 2 — digunakan untuk pivot per-segment di UI.
            $cohorts = $calculator->getCohorts($this->calculationPeriod);
            $matrixRows = [];

            foreach ($cohorts as [$startPeriod, $endPeriod]) {
                foreach ($builder->buildRows($usageType, $startPeriod, $endPeriod, $this->officeCode, $this->akadCode) as $row) {
                    $matrixRows[] = [
                        'from_quality_grade_id' => $row['from_quality_grade_id'],
                        'to_quality_grade_id' => $row['to_quality_grade_id'],
                        'cohort_period' => $startPeriod,
                        'migration_rate' => $row['migration_rate'],
                        'source_outstanding' => $row['source_outstanding'],
                        'destination_outstanding' => $row['destination_outstanding'],
                    ];
                }
            }

            $writer->writePdMigrationMatrix(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                rows: $matrixRows,
                officeCode: $this->officeCode,
                akadCode: $this->akadCode,
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

    private function writeSegmentResult(
        SnapshotWriter $writer,
        CalculationRunLog $runLog,
        UsageType $usageType,
        array $segment,
    ): void {
        $officeCode = $segment['office_code'] ?? null;
        $akadCode = $segment['akad_code'] ?? null;

        $matrixCount = (int) CalculationDataRange::resolveValue(
            CalculationMethodKey::PdMigration,
            'pd_migration_matrix_count',
            officeCode: $officeCode,
            usageType: (int) $this->usageType,
            akadCode: $akadCode,
            default: null,
        ) ?? 12;

        $builder = new MigrationMatrixBuilder;
        $calculator = new PdMigrationCalculator($builder, $matrixCount);

        $pdRates = $calculator->calculate($usageType, $this->calculationPeriod, $officeCode, $akadCode);

        $anchor = PeriodHelper::anchorQuarter($this->calculationPeriod);
        $earliestEnd = PeriodHelper::shiftBack($anchor, ($matrixCount - 1) * 3);
        $dataStart = PeriodHelper::shiftBack($earliestEnd, 12);
        $dataEnd = $this->calculationPeriod;
        $cohortCount = $matrixCount;

        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $this->usageType);
        $notes = sprintf(
            "Dasar data PD Migration [%s]:\n"
                ."- Sumber: financing_outstanding_quarterly (posisi awal & akhir matriks) + financing_account_periods (writeoff)\n"
                ."- Filter: akad eligible: %s; akad 03 hanya jika JTP saat writeoff (maturity<=writeoff_date)\n"
                ."- Anchor Quarter (T): %s; jumlah matriks migrasi = %d (M1=T, Mk = T - 3(k-1) bln), masing-masing dilacak 12 bln\n"
                ."- Data range: %s s.d. %s\n"
                .'- matrix_count=%d',
            $usageType->label(),
            AkadEligibilityService::formatCodes($akadCodes),
            $anchor,
            $matrixCount,
            $dataStart,
            $dataEnd,
            $matrixCount,
        );

        $writer->writePdMigrationResult(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $this->calculationPeriod,
            dataStart: $dataStart,
            dataEnd: $dataEnd,
            cohortCount: $cohortCount,
            pdRates: $pdRates,
            notes: $notes,
            officeCode: $officeCode,
            akadCode: $akadCode,
        );

        $cohorts = $calculator->getCohorts($this->calculationPeriod);
        $matrixRows = [];

        foreach ($cohorts as [$startPeriod, $endPeriod]) {
            foreach ($builder->buildRows($usageType, $startPeriod, $endPeriod, $officeCode, $akadCode) as $row) {
                $matrixRows[] = [
                    'from_quality_grade_id' => $row['from_quality_grade_id'],
                    'to_quality_grade_id' => $row['to_quality_grade_id'],
                    'cohort_period' => $startPeriod,
                    'migration_rate' => $row['migration_rate'],
                    'source_outstanding' => $row['source_outstanding'],
                    'destination_outstanding' => $row['destination_outstanding'],
                ];
            }
        }

        $writer->writePdMigrationMatrix(
            runLog: $runLog,
            usageType: $usageType,
            calculationPeriod: $this->calculationPeriod,
            rows: $matrixRows,
            officeCode: $officeCode,
            akadCode: $akadCode,
        );
    }
}
