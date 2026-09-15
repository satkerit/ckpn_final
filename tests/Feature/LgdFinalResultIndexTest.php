<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\LgdFinalCalculationJob;
use App\Livewire\Lgd\LgdFinalResultIndex;
use App\Models\CalculationRunLog;
use App\Models\LgdCollateralShortfallBySegmentResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\LgdFinalResult;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Verifikasi state machine tombol halaman LGD Final (Hitung/Tampilkan/Rekalkulasi/Hapus).
 * Ref: PRD Bab 11, AGENTS.md §7
 */

// ── Helper: seed snapshot prasyarat (ER + CS) untuk satu segmen ───────────────

function lgdFinalPrerequisites(string $period, int $usageType = 1): void
{
    $runLogEr = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdEr,
        'period' => $period,
        'usage_type' => $usageType,
        'status' => RunStatus::Completed,
    ]);
    LgdExpectedRecoveriesResult::create([
        'calculation_run_log_id' => $runLogEr->id,
        'usage_type' => $usageType,
        'calculation_period' => $period,
        'data_period_start' => substr($period, 0, 4).'01',
        'data_period_end' => $period,
        'window_years' => 1,
        'total_writeoff_amount' => 500_000_000,
        'total_recovery_amount' => 200_000_000,
        'expected_recovery_rate' => 0.4,
        'lgd_rate' => 0.6,
        'is_all_account' => false,
    ]);

    $runLogCs = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => $period,
        'usage_type' => $usageType,
        'status' => RunStatus::Completed,
    ]);
    LgdCollateralShortfallBySegmentResult::create([
        'calculation_run_log_id' => $runLogCs->id,
        'usage_type' => $usageType,
        'calculation_period' => $period,
        'account_count' => 10,
        'total_outstanding' => 300_000_000,
        'total_collateral_net_value' => 150_000_000,
        'total_shortfall' => 150_000_000,
        'avg_lgd_rate' => 0.5,
    ]);
}

function lgdFinalSnapshot(CalculationRunLog $runLog): LgdFinalResult
{
    return LgdFinalResult::create([
        'calculation_run_log_id' => $runLog->id,
        'usage_type' => $runLog->usage_type instanceof UsageType
            ? $runLog->usage_type->value
            : $runLog->getRawOriginal('usage_type'),
        'calculation_period' => $runLog->period,
        'er_total_writeoff_amount' => 500_000_000,
        'er_total_recovery_amount' => 200_000_000,
        'cs_total_outstanding' => 300_000_000,
        'cs_total_collateral_net_value' => 150_000_000,
        'total_recover' => 350_000_000,
        'total_os' => 800_000_000,
        'lgd_final_rate' => 0.5625,
    ]);
}

// ── State machine tests ───────────────────────────────────────────────────────

it('menganggap periode tanpa hasil sebagai state hitung', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(LgdFinalResultIndex::class)
        ->set('runPeriode', '202506')
        ->assertSet('runPeriodeHasResult', false)
        ->assertSet('showResults', false)
        ->call('confirmJalankan')
        ->assertSet('confirmingAction', 'hitung');
});

it('menampilkan data snapshot lalu menghapus hasil dan run log', function (): void {
    $this->actingAs(User::factory()->create());

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdFinal,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);
    lgdFinalSnapshot($runLog);

    Livewire::test(LgdFinalResultIndex::class)
        ->set('runPeriode', '202506')
        ->assertSet('runPeriodeHasResult', true)
        ->assertSet('showResults', false)
        ->call('tampilkanData')
        ->assertSet('showResults', true)
        ->assertSet('filterPeriode', '202506')
        ->call('hapusPerhitungan')
        ->assertRedirect(route('lgd.final.results.index'));

    expect(LgdFinalResult::count())->toBe(0)
        ->and(CalculationRunLog::where('run_type', RunType::LgdFinal)->count())->toBe(0);
});

it('rekalkulasi membuat run log baru dan tidak menghapus run log lama', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdFinal,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);
    lgdFinalSnapshot($runLog);

    Livewire::test(LgdFinalResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('rekalkulasiPerhitungan')
        ->assertSet('isRunning', true);

    // Run log lama tetap ada (history terjaga) + run log baru per UsageType
    expect(CalculationRunLog::where('period', '202506')->where('run_type', RunType::LgdFinal)->count())
        ->toBe(1 + count(UsageType::cases()));

    Queue::assertPushed(LgdFinalCalculationJob::class, count(UsageType::cases()));
});

it('menolak rekalkulasi ketika run log berstatus approved', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdFinal,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Approved,
    ]);

    Livewire::test(LgdFinalResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('rekalkulasiPerhitungan');

    Queue::assertNothingPushed();
    expect(CalculationRunLog::count())->toBe(1);
});

it('job menulis snapshot LGD final dan menandai run log completed', function (): void {
    $this->actingAs(User::factory()->create());

    // Seed prasyarat: snapshot ER dan CS untuk UsageType 1 (ModalKerja)
    lgdFinalPrerequisites('202506', UsageType::ModalKerja->value);

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdFinal,
        'period' => '202506',
        'usage_type' => UsageType::ModalKerja->value,
        'status' => RunStatus::Pending,
    ]);

    (new LgdFinalCalculationJob($runLog->id, UsageType::ModalKerja->value, '202506'))->handle();

    $snapshot = LgdFinalResult::sole();
    expect($snapshot->calculation_run_log_id)->toBe($runLog->id)
        ->and($snapshot->usage_type)->toBe(UsageType::ModalKerja)
        ->and($snapshot->calculation_period)->toBe('202506')
        ->and((float) $snapshot->er_total_writeoff_amount)->toBe(500_000_000.0)
        ->and((float) $snapshot->er_total_recovery_amount)->toBe(200_000_000.0)
        ->and((float) $snapshot->cs_total_outstanding)->toBe(300_000_000.0)
        ->and((float) $snapshot->cs_total_collateral_net_value)->toBe(150_000_000.0)
        ->and((float) $snapshot->total_recover)->toBe(350_000_000.0)
        ->and((float) $snapshot->total_os)->toBe(800_000_000.0)
        ->and((float) $snapshot->lgd_final_rate)->toBe(0.5625)
        ->and($runLog->refresh()->status)->toBe(RunStatus::Completed);
});

it('job menandai run log failed ketika snapshot prasyarat tidak ada', function (): void {
    $this->actingAs(User::factory()->create());

    // Tidak ada snapshot ER/CS → kalkulator melempar RuntimeException
    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdFinal,
        'period' => '202506',
        'usage_type' => UsageType::ModalKerja->value,
        'status' => RunStatus::Pending,
    ]);

    expect(fn () => (new LgdFinalCalculationJob($runLog->id, UsageType::ModalKerja->value, '202506'))->handle())
        ->toThrow(RuntimeException::class);

    expect($runLog->refresh()->status)->toBe(RunStatus::Failed);
    expect(LgdFinalResult::count())->toBe(0);
});
