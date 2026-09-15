<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Pd\Migration\MigrationMatrixBuilder;
use App\Domain\Ckpn\Pd\Migration\PdMigrationCalculator;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\PeriodHelper;
use App\Domain\Ckpn\Services\SnapshotWriter;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationParameter;
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
            // Ambil jumlah matriks migrasi dari calculation_parameters (Ref: AGENTS.md §9 — jangan hardcode)
            $matrixCount = (int) (CalculationParameter::where('parameter_key', 'pd_migration_matrix_count')
                ->where(fn ($q) => $q->where('usage_type', $this->usageType)->orWhereNull('usage_type'))
                ->orderByRaw('usage_type IS NULL ASC')
                ->value('parameter_value') ?? 4);

            $builder = new MigrationMatrixBuilder;
            $calculator = new PdMigrationCalculator($builder, $matrixCount);
            $writer = new SnapshotWriter;

            $pdRates = $calculator->calculate($usageType, $this->calculationPeriod);

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
            );

            // Simpan detail matriks migrasi per cohort (satu baris per from_grade → to_grade/WO).
            // Ref: pd-migration.md Bab 2 — digunakan untuk pivot per-segment di UI.
            $cohorts = $calculator->getCohorts($this->calculationPeriod);
            $matrixRows = [];

            foreach ($cohorts as [$startPeriod, $endPeriod]) {
                foreach ($builder->buildRows($usageType, $startPeriod, $endPeriod) as $row) {
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
