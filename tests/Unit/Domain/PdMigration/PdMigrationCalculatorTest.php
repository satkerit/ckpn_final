<?php

declare(strict_types=1);

use App\Domain\Ckpn\Pd\Migration\MigrationMatrixBuilder;
use App\Domain\Ckpn\Pd\Migration\PdMigrationCalculator;
use App\Domain\Ckpn\Services\PeriodHelper;

test('anchorQuarter maps target month to anchor quarter per pd-migration.md Bab 1', function () {
    // 01,02,03 -> 12 (tahun sebelumnya)
    expect(PeriodHelper::anchorQuarter('202601'))->toBe('202512');
    expect(PeriodHelper::anchorQuarter('202602'))->toBe('202512');
    expect(PeriodHelper::anchorQuarter('202603'))->toBe('202512');
    // 04,05,06 -> 03
    expect(PeriodHelper::anchorQuarter('202604'))->toBe('202603');
    expect(PeriodHelper::anchorQuarter('202606'))->toBe('202603');
    // 07,08,09 -> 06
    expect(PeriodHelper::anchorQuarter('202607'))->toBe('202606');
    // 10,11,12 -> 09
    expect(PeriodHelper::anchorQuarter('202610'))->toBe('202609');
    expect(PeriodHelper::anchorQuarter('202612'))->toBe('202609');
});

test('resolveCohorts menghasilkan matrixCount pasangan periode 12 bulan', function () {
    $calculator = new PdMigrationCalculator(new MigrationMatrixBuilder, matrixCount: 4);
    $reflection = new ReflectionClass($calculator);
    $method = $reflection->getMethod('resolveCohorts');
    $method->setAccessible(true);

    $cohorts = $method->invoke($calculator, '202608');

    // matrixCount = 4
    expect($cohorts)->toHaveCount(4);

    foreach ($cohorts as [$startPeriod, $endPeriod]) {
        expect(PeriodHelper::diffMonths($startPeriod, $endPeriod))->toBe(12);
        expect($endPeriod)->toBeLessThanOrEqual('202608');
        // end (anchor quarter turunan) harus bulan triwulanan
        expect(in_array(substr($endPeriod, 4, 2), ['03', '06', '09', '12']))->toBeTrue();
    }
});

test('resolveCohorts anchors at T and steps back 3 months per matrix', function () {
    // target 202608 -> T = 202606
    $calculator = new PdMigrationCalculator(new MigrationMatrixBuilder, matrixCount: 4);
    $reflection = new ReflectionClass($calculator);
    $method = $reflection->getMethod('resolveCohorts');
    $method->setAccessible(true);

    $cohorts = $method->invoke($calculator, '202608');

    // M1 end = T = 202606, M2 = 202603, M3 = 202512, M4 = 202509
    expect($cohorts[0][1])->toBe('202606');
    expect($cohorts[1][1])->toBe('202603');
    expect($cohorts[2][1])->toBe('202512');
    expect($cohorts[3][1])->toBe('202509');
    // start masing-masing -12 bulan dari end
    expect($cohorts[0][0])->toBe('202506');
    expect($cohorts[3][0])->toBe('202409');
});

test('resolveCohorts respects matrixCount', function () {
    $calculator = new PdMigrationCalculator(new MigrationMatrixBuilder, matrixCount: 2);
    $reflection = new ReflectionClass($calculator);
    $method = $reflection->getMethod('resolveCohorts');
    $method->setAccessible(true);

    $cohorts = $method->invoke($calculator, '202608');

    expect($cohorts)->toHaveCount(2);
    expect($cohorts[0][1])->toBe('202606');
    expect($cohorts[1][1])->toBe('202603');
});

test('resolveCohorts menghasilkan matriks 12 bulan untuk target Maret', function () {
    // target 202603 -> T = 202512 (tahun sebelumnya karena bulan <= 03)
    $calculator = new PdMigrationCalculator(new MigrationMatrixBuilder, matrixCount: 4);
    $reflection = new ReflectionClass($calculator);
    $method = $reflection->getMethod('resolveCohorts');
    $method->setAccessible(true);

    $cohorts = $method->invoke($calculator, '202603');

    expect($cohorts[0][1])->toBe('202512');
    expect($cohorts[1][1])->toBe('202509');
    expect($cohorts[2][1])->toBe('202506');
    expect($cohorts[3][1])->toBe('202503');
});
