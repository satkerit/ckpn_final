<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Jobs\CkpnCollectiveCalculationJob;
use App\Livewire\Ckpn\CkpnCollectiveResultIndex;
use App\Models\Bucket;
use App\Models\CalculationRunLog;
use App\Models\CkpnCollectiveResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\PdNetflowResult;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Verifikasi state machine tombol halaman CKPN Kolektif (Hitung/Tampilkan/Rekalkulasi/Hapus)
 * mengikuti pola PD Netflow + proteksi anti double proses. Ref: PRD Bab 11, AGENTS.md §4 & §7
 */

/** Seed PD Netflow + LGD ER untuk semua UsageType agar guard ketersediaan lolos. */
function seedPdAndLgd(string $period): void
{
    $bucket = Bucket::factory()->create();

    foreach ([1, 2, 3] as $usageType) {
        $pdLog = CalculationRunLog::factory()->create([
            'run_type' => RunType::PdNetflow,
            'period' => $period,
            'usage_type' => $usageType,
            'status' => RunStatus::Completed,
        ]);
        PdNetflowResult::create([
            'calculation_run_log_id' => $pdLog->id,
            'usage_type' => $usageType,
            'from_bucket_id' => $bucket->id,
            'calculation_period' => $period,
            'pd_rate' => 0.05,
            'data_period_start' => '202501',
            'data_period_end' => $period,
            'window_months' => 6,
        ]);

        $lgdLog = CalculationRunLog::factory()->create([
            'run_type' => RunType::LgdEr,
            'period' => $period,
            'usage_type' => $usageType,
            'status' => RunStatus::Completed,
        ]);
        LgdExpectedRecoveriesResult::create([
            'calculation_run_log_id' => $lgdLog->id,
            'usage_type' => $usageType,
            'calculation_period' => $period,
            'data_period_start' => '202006',
            'data_period_end' => $period,
            'window_years' => 5,
            'total_writeoff_amount' => 900000,
            'total_recovery_amount' => 300000,
            'expected_recovery_rate' => 0.3333,
            'lgd_rate' => 0.6667,
            'is_all_account' => false,
        ]);
    }
}

it('menolak hitung ulang ketika periode sudah pernah dihitung (anti double proses)', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    seedPdAndLgd('202506');
    CalculationRunLog::factory()->create([
        'run_type' => RunType::CkpnCollective,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);

    Livewire::test(CkpnCollectiveResultIndex::class)
        ->set('runPeriode', '202506')
        ->assertSet('runPeriodeHasResult', false) // belum ada snapshot, tapi run log Completed sudah ada
        ->call('confirmJalankan')
        ->assertSet('confirmingAction', '') // ditolak: arahkan ke Rekalkulasi
        ->call('jalankanPerhitungan')
        ->assertSet('confirmingAction', '');

    Queue::assertNothingPushed();
});

it('menolak hitung ulang ketika job periode sama masih berjalan', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    seedPdAndLgd('202506');
    foreach ([1, 2, 3] as $usageType) {
        CalculationRunLog::factory()->create([
            'run_type' => RunType::CkpnCollective,
            'period' => '202506',
            'usage_type' => $usageType,
            'status' => RunStatus::Processing,
        ]);
    }

    Livewire::test(CkpnCollectiveResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('confirmJalankan')
        ->assertSet('confirmingAction', 'hitung')
        ->call('jalankanPerhitungan');

    Queue::assertNothingPushed(); // usage type aktif di-skip, tidak ada job baru
});

it('menghitung periode baru lalu menampilkan dan menghapus hasilnya', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    seedPdAndLgd('202506');

    Livewire::test(CkpnCollectiveResultIndex::class)
        ->set('runPeriode', '202506')
        ->assertSet('runPeriodeHasResult', false)
        ->call('confirmJalankan')
        ->assertSet('confirmingAction', 'hitung')
        ->call('jalankanPerhitungan');

    Queue::assertPushedTimes(CkpnCollectiveCalculationJob::class, 3); // satu job per UsageType
    Queue::fake();

    // Simulasikan job selesai: buat snapshot + tandai run log Completed
    $runLog = CalculationRunLog::where('run_type', RunType::CkpnCollective->value)
        ->where('period', '202506')
        ->first();
    $runLog->update(['status' => RunStatus::Completed]);
    CkpnCollectiveResult::factory()->create([
        'calculation_run_log_id' => $runLog->id,
        'calculation_period' => '202506',
        'usage_type' => $runLog->usage_type,
    ]);

    Livewire::test(CkpnCollectiveResultIndex::class)
        ->set('runPeriode', '202506')
        ->assertSet('runPeriodeHasResult', true)
        ->assertSet('showResults', false)
        ->call('tampilkanData')
        ->assertSet('showResults', true)
        ->assertSet('filterPeriode', '202506')
        ->call('hapusPerhitungan')
        ->assertRedirect(route('ckpn.collective.index'));

    expect(CkpnCollectiveResult::count())->toBe(0)
        ->and(CalculationRunLog::where('run_type', RunType::CkpnCollective->value)->count())->toBe(0);
});

it('rekalkulasi membuat run log baru tanpa menghapus snapshot lama', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    seedPdAndLgd('202506');
    $runLog = CalculationRunLog::factory()->create([
        'run_type' => RunType::CkpnCollective,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Completed,
    ]);
    CkpnCollectiveResult::factory()->create([
        'calculation_run_log_id' => $runLog->id,
        'calculation_period' => '202506',
        'usage_type' => 1,
    ]);

    Livewire::test(CkpnCollectiveResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('confirmRekalkulasi')
        ->assertSet('confirmingAction', 'rekalkulasi')
        ->call('rekalkulasi')
        ->assertSet('showResults', true);

    Queue::assertPushedTimes(CkpnCollectiveCalculationJob::class, 3);
    expect(CkpnCollectiveResult::count())->toBe(1) // snapshot lama tetap ada
        ->and(CalculationRunLog::where('run_type', RunType::CkpnCollective->value)->count())->toBe(4); // 1 lama + 3 baru
});

it('menolak rekalkulasi dan hapus ketika run log berstatus approved', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    seedPdAndLgd('202506');
    CalculationRunLog::factory()->create([
        'run_type' => RunType::CkpnCollective,
        'period' => '202506',
        'usage_type' => 1,
        'status' => RunStatus::Approved,
    ]);

    Livewire::test(CkpnCollectiveResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('rekalkulasi')
        ->call('hapusPerhitungan');

    Queue::assertNothingPushed();
    expect(CalculationRunLog::where('run_type', RunType::CkpnCollective->value)->count())->toBe(1);
});

it('menolak hitung ketika data PD atau LGD belum tersedia', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create());

    // Tanpa seed PD/LGD — guard harus menolak
    Livewire::test(CkpnCollectiveResultIndex::class)
        ->set('runPeriode', '202506')
        ->call('confirmJalankan')
        ->assertSet('confirmingAction', '');

    Queue::assertNothingPushed();
});
