<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\LgdCsCalculationJob;
use App\Livewire\Lgd\LgdCsResultIndex;
use App\Models\CalculationRunLog;
use App\Models\Collateral;
use App\Models\FinancingAccount;
use App\Models\FinancingAccountPeriod;
use App\Models\LgdCollateralShortfallBySegmentResult;
use App\Models\LgdCollateralShortfallResult;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Verifikasi state machine tombol halaman LGD CS (Hitung/Tampilkan/Rekalkulasi/Hapus)
 * mengikuti pola PD Netflow. Ref: PRD Bab 10, AGENTS.md §7
 */
function lgdCsSnapshot(CalculationRunLog $runLog): LgdCollateralShortfallResult
{
    return LgdCollateralShortfallResult::create([
        'calculation_run_log_id' => $runLog->id,
        'financing_account_id' => FinancingAccount::factory()->create()->id,
        'usage_type' => 1,
        'calculation_period' => $runLog->period,
        'outstanding_balance' => 1000000,
        'collateral_net_value' => 750000,
        'shortfall' => 250000,
        'lgd_rate' => 0.25,
    ]);
}

it('menganggap periode tanpa hasil sebagai state hitung', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(LgdCsResultIndex::class)
        ->set('runPeriode', '202506')
        ->assertSet('runPeriodeHasResult', false)
        ->assertSet('showResults', false)
        ->call('confirmJalankan')
        ->assertSet('confirmingAction', 'hitung');
});

it('menampilkan data snapshot lalu menghapus hasil dan run log', function (): void {
    $this->actingAs(User::factory()->create());

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);
    lgdCsSnapshot($runLog);
    LgdCollateralShortfallBySegmentResult::create([
        'calculation_run_log_id' => $runLog->id,
        'usage_type' => 1,
        'calculation_period' => '202506',
        'account_count' => 1,
        'total_outstanding' => 1000000,
        'total_collateral_net_value' => 750000,
        'total_shortfall' => 250000,
        'avg_lgd_rate' => 0.25,
    ]);

    Livewire::test(LgdCsResultIndex::class)
        ->set('runPeriode', '202506')
        ->assertSet('runPeriodeHasResult', true)
        ->assertSet('showResults', false)
        ->call('tampilkanData')
        ->assertSet('showResults', true)
        ->assertSet('filterPeriode', '202506')
        ->call('hapusPerhitungan')
        ->assertRedirect(route('lgd.cs.results.index'));

    expect(LgdCollateralShortfallResult::count())->toBe(0)
        ->and(LgdCollateralShortfallBySegmentResult::count())->toBe(0)
        ->and(CalculationRunLog::count())->toBe(0);
});

it('rekalkulasi membuat run log baru dan tidak mengubah snapshot lama', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);
    lgdCsSnapshot($runLog);

    Livewire::test(LgdCsResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('rekalkulasi')
        ->assertSet('isRunning', true);

    // Satu run log baru per UsageType, run log lama tetap ada (history terjaga)
    expect(CalculationRunLog::where('period', '202506')->where('run_type', RunType::LgdCs)->count())
        ->toBe(1 + count(UsageType::cases()));

    Queue::assertPushed(LgdCsCalculationJob::class, count(UsageType::cases()));
});

it('menolak rekalkulasi ketika run log berstatus approved', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Approved,
    ]);

    Livewire::test(LgdCsResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('rekalkulasi');

    Queue::assertNothingPushed();
    expect(CalculationRunLog::count())->toBe(1);
});

it('mengunduh export excel snapshot LGD CS sesuai filter periode', function (): void {
    $this->actingAs(User::factory()->create());

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);
    lgdCsSnapshot($runLog);

    Livewire::test(LgdCsResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('tampilkanData')
        ->call('exportExcel')
        ->assertFileDownloaded('lgd-collateral-shortfall-202506.xlsx');
});

it('job menulis snapshot detail dan agregat per segmen lalu menandai run log completed', function (): void {
    $this->actingAs(User::factory()->create());

    // 1 akun eligible: kualitas 5, outstanding 1jt, agunan estimated sale 600rb
    // → shortfall 400rb, LGD 0.4 (contoh manual — Ref: PRD Bab 10)
    $account = FinancingAccount::factory()->create(['usage_type' => 1]);
    FinancingAccountPeriod::factory()->create([
        'financing_account_id' => $account->id,
        'period' => '202506',
        'outstanding_balance' => 1000000,
        'collectibility' => 5,
    ]);
    Collateral::factory()->create([
        'financing_account_id' => $account->id,
        'estimated_sale_value' => 600000,
    ]);

    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::LgdCs,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Pending,
    ]);

    (new LgdCsCalculationJob($runLog->id, 1, '202506'))->handle();

    expect(LgdCollateralShortfallResult::count())->toBe(1);

    $aggregate = LgdCollateralShortfallBySegmentResult::sole();
    expect($aggregate->calculation_run_log_id)->toBe($runLog->id)
        ->and($aggregate->usage_type)->toBe(UsageType::ModalKerja)
        ->and($aggregate->calculation_period)->toBe('202506')
        ->and($aggregate->account_count)->toBe(1)
        ->and((float) $aggregate->total_outstanding)->toBe(1000000.0)
        ->and((float) $aggregate->total_collateral_net_value)->toBe(600000.0)
        ->and((float) $aggregate->total_shortfall)->toBe(400000.0)
        ->and((float) $aggregate->avg_lgd_rate)->toBe(0.4)
        ->and($runLog->refresh()->status)->toBe(RunStatus::Completed);
});
