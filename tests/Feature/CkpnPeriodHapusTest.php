<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Livewire\Ckpn\CkpnPeriodIndex;
use App\Models\Bucket;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\FinancingAccount;
use App\Models\FinancingAccountPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Verifikasi cascade delete periode CKPN — semua data kalkulasi terkait ikut terhapus,
 * tapi financing_account_periods (data historis) tidak tersentuh. Ref: PRD Bab 12a
 */
function buatRunLog(string $period, RunType $runType): CalculationRunLog
{
    return CalculationRunLog::factory()->create([
        'period' => $period,
        'run_type' => $runType,
        'status' => RunStatus::Completed,
    ]);
}

it('menghapus seluruh data kalkulasi ketika periode dihapus', function (): void {
    $user = User::factory()->create();
    $period = CkpnPeriod::create(['period' => '202501', 'status' => 'draft']);

    // --- Seed data PD ---
    $bucket = Bucket::factory()->create();
    $pdLog = buatRunLog('202501', RunType::PdNetflow);
    DB::table('pd_netflow_result')->insert([
        'calculation_run_log_id' => $pdLog->id,
        'usage_type' => 1,
        'from_bucket_id' => $bucket->id,
        'calculation_period' => '202501',
        'pd_rate' => 0.05,
        'data_period_start' => '202401',
        'data_period_end' => '202501',
        'window_months' => 12,
    ]);

    // --- Seed data LGD ER ---
    $lgdErLog = buatRunLog('202501', RunType::LgdEr);
    DB::table('lgd_expected_recoveries_result')->insert([
        'calculation_run_log_id' => $lgdErLog->id,
        'usage_type' => 1,
        'calculation_period' => '202501',
        'data_period_start' => '202001',
        'data_period_end' => '202501',
        'window_years' => 5,
        'total_writeoff_amount' => 1000000,
        'total_recovery_amount' => 600000,
        'expected_recovery_rate' => 0.60,
        'lgd_rate' => 0.40,
        'is_all_account' => true,
    ]);

    // --- Seed data LGD CS ---
    $lgdCsLog = buatRunLog('202501', RunType::LgdCs);
    $account = FinancingAccount::factory()->create();
    DB::table('lgd_collateral_shortfall_result')->insert([
        'calculation_run_log_id' => $lgdCsLog->id,
        'financing_account_id' => $account->id,
        'calculation_period' => '202501',
        'outstanding_balance' => 500000,
        'collateral_net_value' => 300000,
        'shortfall' => 200000,
        'lgd_rate' => 0.40,
    ]);
    DB::table('lgd_collateral_shortfall_by_segment_result')->insert([
        'calculation_run_log_id' => $lgdCsLog->id,
        'usage_type' => 1,
        'calculation_period' => '202501',
        'account_count' => 1,
        'total_outstanding' => 500000,
        'total_collateral_net_value' => 300000,
        'total_shortfall' => 200000,
        'avg_lgd_rate' => 0.40,
    ]);

    // --- Seed data LGD Final ---
    $lgdFinalLog = buatRunLog('202501', RunType::LgdFinal);
    DB::table('lgd_final_result')->insert([
        'calculation_run_log_id' => $lgdFinalLog->id,
        'usage_type' => 1,
        'calculation_period' => '202501',
        'er_total_writeoff_amount' => 1000000,
        'er_total_recovery_amount' => 600000,
        'cs_total_outstanding' => 500000,
        'cs_total_collateral_net_value' => 300000,
        'total_recover' => 900000,
        'total_os' => 1500000,
        'lgd_final_rate' => 0.40,
    ]);

    // --- Seed data CKPN Individual & Kolektif ---
    $indivLog = buatRunLog('202501', RunType::CkpnIndividual);
    DB::table('ckpn_individual_result')->insert([
        'calculation_run_log_id' => $indivLog->id,
        'financing_account_id' => $account->id,
        'calculation_period' => '202501',
        'outstanding_balance' => 500000,
        'total_collateral_liquidation_value' => 300000,
        'selling_cost_rate' => 0.05,
        'selling_cost_amount' => 15000,
        'ckpn_amount' => 10000,
        'collectibility' => 3,
    ]);
    $collLog = buatRunLog('202501', RunType::CkpnCollective);
    DB::table('ckpn_collective_result')->insert([
        'calculation_run_log_id' => $collLog->id,
        'usage_type' => 1,
        'financing_account_id' => $account->id,
        'calculation_period' => '202501',
        'pd_method_used' => 'netflow',
        'pd_rate' => 0.05,
        'lgd_method_used' => 'er',
        'lgd_rate' => 0.40,
        'ead' => 500000,
        'ckpn_amount' => 10000,
    ]);

    // --- Seed financing_account_periods (data historis — TIDAK boleh terhapus) ---
    $fap = FinancingAccountPeriod::factory()->create([
        'financing_account_id' => $account->id,
        'period' => '202501',
    ]);

    // --- Hapus periode ---
    $component = Livewire::actingAs($user)
        ->test(CkpnPeriodIndex::class);
    $component->set('deletingId', $period->id);
    $component->call('hapus');

    // Verifikasi semua data kalkulasi terhapus
    expect(DB::table('ckpn_periods')->where('id', $period->id)->exists())->toBeFalse();
    expect(DB::table('pd_netflow_result')->where('calculation_run_log_id', $pdLog->id)->exists())->toBeFalse();
    expect(DB::table('lgd_expected_recoveries_result')->where('calculation_run_log_id', $lgdErLog->id)->exists())->toBeFalse();
    expect(DB::table('lgd_collateral_shortfall_result')->where('calculation_run_log_id', $lgdCsLog->id)->exists())->toBeFalse();
    expect(DB::table('lgd_collateral_shortfall_by_segment_result')->where('calculation_run_log_id', $lgdCsLog->id)->exists())->toBeFalse();
    expect(DB::table('lgd_final_result')->where('calculation_run_log_id', $lgdFinalLog->id)->exists())->toBeFalse();
    expect(DB::table('ckpn_individual_result')->where('calculation_period', '202501')->exists())->toBeFalse();
    expect(DB::table('ckpn_collective_result')->where('calculation_period', '202501')->exists())->toBeFalse();

    // Verifikasi run log juga terhapus
    expect(DB::table('calculation_run_log')->whereIn('id', [$pdLog->id, $lgdErLog->id, $lgdCsLog->id, $lgdFinalLog->id])->exists())->toBeFalse();

    // Verifikasi data historis financing_account_periods TIDAK terhapus
    expect(DB::table('financing_account_periods')->where('financing_account_id', $account->id)->exists())->toBeTrue();
});

it('tidak menghapus periode yang berstatus approved', function (): void {
    $user = User::factory()->create();
    $period = CkpnPeriod::create(['period' => '202502', 'status' => 'approved']);

    $component = Livewire::actingAs($user)
        ->test(CkpnPeriodIndex::class);
    $component->set('deletingId', $period->id);
    $component->call('hapus');

    expect(DB::table('ckpn_periods')->where('id', $period->id)->exists())->toBeTrue();
    expect($component->get('flashType'))->toBe('error');
});

it('tidak melakukan apa-apa jika deletingId null', function (): void {
    $user = User::factory()->create();
    $count = DB::table('ckpn_periods')->count();

    Livewire::actingAs($user)
        ->test(CkpnPeriodIndex::class)
        ->call('hapus');

    expect(DB::table('ckpn_periods')->count())->toBe($count);
});
