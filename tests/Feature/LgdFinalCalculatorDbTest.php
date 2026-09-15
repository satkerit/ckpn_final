<?php

declare(strict_types=1);

use App\Domain\Ckpn\Lgd\LgdFinalCalculator;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\LgdCollateralShortfallBySegmentResult;
use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature tests untuk LgdFinalCalculator yang membutuhkan DB.
 * Ref: PRD Bab 11
 */
test('calculateForSegment menghitung LGD final dari snapshot ER dan CS', function (): void {
    $runLogEr = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdEr,
        'period' => '202506',
        'usage_type' => UsageType::ModalKerja->value,
        'status' => RunStatus::Completed,
    ]);
    LgdExpectedRecoveriesResult::create([
        'calculation_run_log_id' => $runLogEr->id,
        'usage_type' => UsageType::ModalKerja->value,
        'calculation_period' => '202506',
        'data_period_start' => '202406',
        'data_period_end' => '202506',
        'window_years' => 1,
        'total_writeoff_amount' => 500_000_000,
        'total_recovery_amount' => 200_000_000,
        'expected_recovery_rate' => 0.4,
        'lgd_rate' => 0.6,
        'is_all_account' => false,
    ]);

    $runLogCs = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => '202506',
        'usage_type' => UsageType::ModalKerja->value,
        'status' => RunStatus::Completed,
    ]);
    LgdCollateralShortfallBySegmentResult::create([
        'calculation_run_log_id' => $runLogCs->id,
        'usage_type' => UsageType::ModalKerja->value,
        'calculation_period' => '202506',
        'account_count' => 10,
        'total_outstanding' => 300_000_000,
        'total_collateral_net_value' => 150_000_000,
        'total_shortfall' => 150_000_000,
        'avg_lgd_rate' => 0.5,
    ]);

    // Recover = 200jt + 150jt = 350jt
    // OS      = 500jt + 300jt = 800jt
    // LGD     = 1 - (350 / 800) = 0.5625
    $result = (new LgdFinalCalculator)->calculateForSegment(UsageType::ModalKerja, '202506');

    expect($result['er_total_writeoff_amount'])->toBe(500_000_000.0)
        ->and($result['er_total_recovery_amount'])->toBe(200_000_000.0)
        ->and($result['cs_total_outstanding'])->toBe(300_000_000.0)
        ->and($result['cs_total_collateral_net_value'])->toBe(150_000_000.0)
        ->and($result['total_recover'])->toBe(350_000_000.0)
        ->and($result['total_os'])->toBe(800_000_000.0)
        ->and($result['lgd_final_rate'])->toBe(0.5625);
});

test('calculateForSegment melempar exception ketika snapshot ER tidak ada', function (): void {
    $runLogCs = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => '202506',
        'usage_type' => UsageType::ModalKerja->value,
        'status' => RunStatus::Completed,
    ]);
    LgdCollateralShortfallBySegmentResult::create([
        'calculation_run_log_id' => $runLogCs->id,
        'usage_type' => UsageType::ModalKerja->value,
        'calculation_period' => '202506',
        'account_count' => 5,
        'total_outstanding' => 100_000_000,
        'total_collateral_net_value' => 80_000_000,
        'total_shortfall' => 20_000_000,
        'avg_lgd_rate' => 0.2,
    ]);

    expect(fn () => (new LgdFinalCalculator)->calculateForSegment(UsageType::ModalKerja, '202506'))
        ->toThrow(RuntimeException::class);
});

test('calculateForSegment melempar exception ketika snapshot CS tidak ada', function (): void {
    $runLogEr = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdEr,
        'period' => '202506',
        'usage_type' => UsageType::ModalKerja->value,
        'status' => RunStatus::Completed,
    ]);
    LgdExpectedRecoveriesResult::create([
        'calculation_run_log_id' => $runLogEr->id,
        'usage_type' => UsageType::ModalKerja->value,
        'calculation_period' => '202506',
        'data_period_start' => '202406',
        'data_period_end' => '202506',
        'window_years' => 1,
        'total_writeoff_amount' => 500_000_000,
        'total_recovery_amount' => 200_000_000,
        'expected_recovery_rate' => 0.4,
        'lgd_rate' => 0.6,
        'is_all_account' => false,
    ]);

    expect(fn () => (new LgdFinalCalculator)->calculateForSegment(UsageType::ModalKerja, '202506'))
        ->toThrow(RuntimeException::class);
});
