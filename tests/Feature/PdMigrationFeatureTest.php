<?php

declare(strict_types=1);

use App\Domain\Ckpn\Pd\Migration\MigrationMatrixBuilder;
use App\Domain\Ckpn\Pd\Migration\PdMigrationCalculator;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\PdMigrationCalculationJob;
use App\Models\CalculationRunLog;
use App\Models\FinancingOutstandingQuarterly;
use App\Models\PdMigrationMatrix;
use App\Models\PdMigrationResult;
use App\Models\QualityGrade;

/**
 * Feature test: PD Migration calculation flow.
 * Ref: PRD Bab 8
 */
test('calculator mengembalikan pd_rate nol untuk semua grade jika tidak ada data cohort', function () {
    $grades = collect([
        QualityGrade::factory()->create(['code' => 'K1', 'collectibility_number' => 1]),
        QualityGrade::factory()->create(['code' => 'K2', 'collectibility_number' => 2]),
        QualityGrade::factory()->create(['code' => 'K3', 'collectibility_number' => 3]),
    ]);

    $builder = new MigrationMatrixBuilder;
    $calculator = new PdMigrationCalculator($builder, matrixCount: 1);

    // calculationPeriod terlalu dekat sehingga tidak ada matriks dengan data
    $results = $calculator->calculate(UsageType::ModalKerja, '202301');

    // Tidak ada matriks → semua PD rate = 0.0
    foreach ($grades as $grade) {
        expect($results[$grade->id] ?? 0.0)->toBe(0.0);
    }
});

test('MigrationMatrixBuilder mengembalikan array kosong jika tidak ada outstanding quarterly', function () {
    $builder = new MigrationMatrixBuilder;

    $matrix = $builder->build(UsageType::ModalKerja, '202203', '202303');

    expect($matrix)->toBeArray()->toBeEmpty();
});

test('MigrationMatrixBuilder menghitung migration rate dari outstanding quarterly', function () {
    $grade = QualityGrade::factory()->create(['collectibility_number' => 1]);

    // Cohort start: 100jt di grade lancar
    FinancingOutstandingQuarterly::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
        'quality_grade_id' => $grade->id,
        'period' => '202203',
        'total_outstanding' => 100_000_000,
    ]);

    // Cohort end: 80jt masih di grade yang sama (20% migrated out)
    FinancingOutstandingQuarterly::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
        'quality_grade_id' => $grade->id,
        'period' => '202303',
        'total_outstanding' => 80_000_000,
    ]);

    $builder = new MigrationMatrixBuilder;
    $matrix = $builder->build(UsageType::ModalKerja, '202203', '202303');

    expect($matrix)->toBeArray();
    expect(isset($matrix[$grade->id]))->toBeTrue();
    // Rate = dest / source = 80jt / 100jt = 0.8
    expect($matrix[$grade->id][$grade->id])->toBe(0.8);
});

test('calculator menghasilkan pd_rate per quality_grade_id', function () {
    $grade = QualityGrade::factory()->create(['collectibility_number' => 3]);

    // Seed data matriks migrasi agar ada hasil.
    // calculationPeriod 202212 -> anchor T = 202209, matrixCount=2
    //   M1: [202109, 202209], M2: [202106, 202206]
    FinancingOutstandingQuarterly::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
        'quality_grade_id' => $grade->id,
        'period' => '202106',
        'total_outstanding' => 200_000_000,
    ]);
    FinancingOutstandingQuarterly::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
        'quality_grade_id' => $grade->id,
        'period' => '202206',
        'total_outstanding' => 150_000_000,
    ]);

    $builder = new MigrationMatrixBuilder;
    $calculator = new PdMigrationCalculator($builder, matrixCount: 2);

    $results = $calculator->calculate(UsageType::ModalKerja, '202212');

    expect($results)->toBeArray();
    expect(isset($results[$grade->id]))->toBeTrue();
    expect($results[$grade->id])->toBeFloat()->toBeGreaterThanOrEqual(0.0);
    expect($results[$grade->id])->toBeLessThanOrEqual(1.0);
});

test('job populates pd_migration_matrix per cohort dan segment', function () {
    $grade = QualityGrade::factory()->create(['collectibility_number' => 3]);

    // Cohort k=1 untuk periode 202212 → [202106, 202206]
    FinancingOutstandingQuarterly::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
        'quality_grade_id' => $grade->id,
        'period' => '202106',
        'total_outstanding' => 200_000_000,
    ]);
    FinancingOutstandingQuarterly::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
        'quality_grade_id' => $grade->id,
        'period' => '202206',
        'total_outstanding' => 150_000_000,
    ]);

    $runLog = CalculationRunLog::factory()->create([
        'period' => '202212',
        'run_type' => RunType::PdMigration,
        'usage_type' => UsageType::ModalKerja,
        'status' => RunStatus::Pending,
    ]);

    (new PdMigrationCalculationJob($runLog->id, UsageType::ModalKerja->value, '202212'))->handle();

    // Hasil PD rate tersimpan
    expect(PdMigrationResult::where('calculation_period', '202212')->exists())->toBeTrue();

    // Detail matriks tersimpan per cohort
    $matrix = PdMigrationMatrix::where('calculation_run_log_id', $runLog->id)->get();
    expect($matrix)->not->toBeEmpty();

    // Baris WO punya to_quality_grade_id = null
    expect($matrix->contains(fn ($m) => $m->to_quality_grade_id === null))->toBeTrue();
    // Semua baris punya usage_type yang benar
    expect($matrix->every(fn ($m) => $m->usage_type === UsageType::ModalKerja))->toBeTrue();
    // cohort_period terisi (start cohort)
    expect($matrix->every(fn ($m) => $m->cohort_period !== null))->toBeTrue();
});
