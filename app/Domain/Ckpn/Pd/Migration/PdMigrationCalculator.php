<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Migration;

use App\Domain\Ckpn\Pd\Contracts\PdCalculationMethodInterface;
use App\Domain\Ckpn\Services\PeriodHelper;
use App\Enums\UsageType;
use App\Models\QualityGrade;

/**
 * Calculates PD using the Migration Matrix method.
 * Ref: PRD Bab 8, pd-migration.md
 *
 * PD(grade_X) = average migration rate to WO across N migration matrices
 * anchored at the anchor quarter T (Bab 1) and stepped back 3 months each (Bab 2).
 */
final class PdMigrationCalculator implements PdCalculationMethodInterface
{
    public function __construct(
        private readonly MigrationMatrixBuilder $matrixBuilder,
        private readonly int $matrixCount = 4,
    ) {}

    /**
     * Menghitung PD Migration Matrix untuk satu segmen dan satu periode.
     *
     * Metode: rata-rata migration rate menuju WO dari seluruh matriks migrasi dalam window.
     *
     * Alur kalkulasi (Ref: pd-migration.md Bab 1-3):
     * 1. Tentukan Anchor Quarter (T) dari calculationPeriod via PeriodHelper::anchorQuarter (Bab 1).
     * 2. Bangun N matriks migrasi (N = matrixCount): M1=T, Mk = T - 3(k-1) bulan,
     *    masing-masing menelusuri 12 bulan (start = Mk - 12) (Bab 2).
     * 3. Untuk setiap matriks, bangun migration matrix menggunakan MigrationMatrixBuilder.
     * 4. Ambil WO rate per quality grade dari setiap matriks.
     * 5. Hitung rata-rata WO rate lintas matriks sebagai PD final per grade (Bab 3).
     *
     * Prasyarat sebelum memanggil fungsi ini:
     * - Data outstanding triwulanan sudah tersedia di `financing_outstanding_quarterly`.
     * - Data writeoff akun sudah ada di `financing_account_periods`.
     * - Parameter `pd_migration_matrix_count` sudah dikonfigurasi (default 4 matriks).
     *
     * Input:
     * - $usageType         : segmen pembiayaan (enum UsageType).
     * - $calculationPeriod : periode perhitungan format yyyymm.
     *
     * Ref: PRD Bab 8, pd-migration.md
     *
     * @return array<int, float> Key = quality_grade_id, value = pd_rate (migration to WO)
     */
    public function calculate(UsageType $usageType, string $calculationPeriod): array
    {
        $cohorts = $this->resolveCohorts($calculationPeriod);
        $allGrades = QualityGrade::orderBy('collectibility_number')->get();

        $pdAccumulator = [];

        foreach ($cohorts as [$cohortPeriod, $endPeriod]) {
            $matrix = $this->matrixBuilder->build($usageType, $cohortPeriod, $endPeriod);

            foreach ($allGrades as $grade) {
                if (! isset($matrix[$grade->id])) {
                    continue;
                }
                $woRate = $matrix[$grade->id]['wo'] ?? 0.0;
                $pdAccumulator[$grade->id][] = $woRate;
            }
        }

        // PD final = average WO rate across all cohorts per grade
        $pdRates = [];
        foreach ($allGrades as $grade) {
            $rates = $pdAccumulator[$grade->id] ?? [];
            $pdRates[$grade->id] = count($rates) > 0
                ? array_sum($rates) / count($rates)
                : 0.0;
        }

        return $pdRates;
    }

    /**
     * Ekpose daftar cohort (pasangan [start, end]) untuk periode kalkulasi.
     * Dipakai job untuk membangun detail matriks per cohort (snapshot pd_migration_matrix).
     * Ref: pd-migration.md Bab 2
     *
     * @return array<int, array{string, string}> List pasangan [cohort_start, cohort_end]
     */
    public function getCohorts(string $calculationPeriod): array
    {
        return $this->resolveCohorts($calculationPeriod);
    }

    /**
     * Menghasilkan tepat $matrixCount pasangan [matrix_start, matrix_end] yang di-anchor
     * pada Anchor Quarter (T) dan mundur 3 bulan per matriks (Ref: pd-migration.md Bab 1-2).
     *
     * Untuk matriks ke-k (k = 0..matrixCount-1):
     *   - end   = T - 3k bulan   (jarak 3 bulan antar matriks, mundur)
     *   - start = end - 12 bulan (setiap matriks menelusuri 1 tahun penuh)
     *
     * Contoh: matrixCount=4, calculationPeriod=202608 → T=202606
     *   M1: [202506, 202606]   M2: [202503, 202603]
     *   M3: [202412, 202512]   M4: [202409, 202509]
     *
     * @return array<int, array{string, string}> List pasangan [matrix_start, matrix_end]
     */
    private function resolveCohorts(string $calculationPeriod): array
    {
        $anchor = PeriodHelper::anchorQuarter($calculationPeriod);

        $cohorts = [];
        for ($k = 0; $k < $this->matrixCount; $k++) {
            $endPeriod = PeriodHelper::shiftBack($anchor, $k * 3);
            $startPeriod = PeriodHelper::shiftBack($endPeriod, 12);
            $cohorts[] = [$startPeriod, $endPeriod];
        }

        return $cohorts;
    }
}
