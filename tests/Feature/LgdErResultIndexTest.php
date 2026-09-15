<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Livewire\Lgd\LgdErResultIndex;
use App\Models\CalculationRunLog;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\User;
use Livewire\Livewire;

/**
 * Verifikasi export Excel halaman LGD ER. Ref: PRD Bab 9
 */
it('mengunduh export excel snapshot LGD ER sesuai filter periode', function (): void {
    $this->actingAs(User::factory()->create());

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdEr,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);

    LgdExpectedRecoveriesResult::create([
        'calculation_run_log_id' => $runLog->id,
        'usage_type' => 1,
        'calculation_period' => '202506',
        'data_period_start' => '202006',
        'data_period_end' => '202506',
        'window_years' => 5,
        'total_writeoff_amount' => 900000,
        'total_recovery_amount' => 300000,
        'expected_recovery_rate' => 0.3333,
        'lgd_rate' => 0.6667,
        'is_all_account' => false,
    ]);

    Livewire::test(LgdErResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('tampilkanData')
        ->call('exportExcel')
        ->assertFileDownloaded('lgd-expected-recoveries-202506.xlsx');
});
