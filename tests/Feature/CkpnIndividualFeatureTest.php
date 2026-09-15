<?php

declare(strict_types=1);

use App\Domain\Ckpn\Individual\CkpnIndividualCalculator;
use App\Enums\UsageType;
use App\Models\CkpnPeriodClassification;
use App\Models\FinancingAccount;
use App\Models\FinancingAccountPeriod;

/**
 * Feature test: CKPN Individual calculation flow.
 * Ref: PRD Bab 6.1
 */
test('calculator mengembalikan array kosong jika tidak ada akun npf', function () {
    $calculator = new CkpnIndividualCalculator(10, 0.05);

    $results = $calculator->calculatePerAccount(UsageType::ModalKerja, '202612');

    expect($results)->toBeEmpty();
});

test('calculator menghitung ckpn untuk akun kolektibilitas 3', function () {
    $account = FinancingAccount::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
        'is_active' => true,
    ]);

    FinancingAccountPeriod::factory()->create([
        'financing_account_id' => $account->id,
        'period' => '202612',
        'outstanding_balance' => 100_000_000,
        'collectibility' => 3,
        'writeoff_status' => null,
    ]);

    CkpnPeriodClassification::factory()->create([
        'financing_account_id' => $account->id,
        'period' => '202612',
        'outstanding_balance' => 100_000_000,
        'collectibility' => 3,
        'classification' => 'individual',
        'is_top_n_outstanding' => true,
    ]);

    $calculator = new CkpnIndividualCalculator(topNOutstanding: 10, sellingCostRate: 0.05);
    $results = $calculator->calculatePerAccount(UsageType::ModalKerja, '202612');

    expect($results)->toHaveCount(1);
    expect($results[0]['collectibility'])->toBe(3);
    expect($results[0]['ckpn_amount'])->toBeGreaterThanOrEqual(0.0);
    expect($results[0]['is_top_n_outstanding'])->toBeTrue();
});

test('hanya akun kolektibilitas 3/4/5 yang masuk kalkulasi', function () {
    // Akun kol-1 (tidak masuk — classification != individual)
    $accountLancar = FinancingAccount::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
    ]);
    FinancingAccountPeriod::factory()->create([
        'financing_account_id' => $accountLancar->id,
        'period' => '202612',
        'outstanding_balance' => 50_000_000,
        'collectibility' => 1,
    ]);
    // Tidak dibuat CkpnPeriodClassification untuk akun kol-1 → tidak masuk

    // Akun kol-5 (masuk)
    $accountMacet = FinancingAccount::factory()->create([
        'usage_type' => UsageType::ModalKerja->value,
    ]);
    FinancingAccountPeriod::factory()->create([
        'financing_account_id' => $accountMacet->id,
        'period' => '202612',
        'outstanding_balance' => 80_000_000,
        'collectibility' => 5,
    ]);
    CkpnPeriodClassification::factory()->create([
        'financing_account_id' => $accountMacet->id,
        'period' => '202612',
        'outstanding_balance' => 80_000_000,
        'collectibility' => 5,
        'classification' => 'individual',
        'is_top_n_outstanding' => true,
    ]);

    $calculator = new CkpnIndividualCalculator(10, 0.05);
    $results = $calculator->calculatePerAccount(UsageType::ModalKerja, '202612');

    expect($results)->toHaveCount(1);
    expect($results[0]['collectibility'])->toBe(5);
});

test('top-n selection benar ketika lebih banyak akun dari n', function () {
    $n = 2;
    $outstandings = [300_000_000, 100_000_000, 500_000_000, 200_000_000];

    foreach ($outstandings as $outstanding) {
        $account = FinancingAccount::factory()->create([
            'usage_type' => UsageType::ModalKerja->value,
        ]);
        FinancingAccountPeriod::factory()->create([
            'financing_account_id' => $account->id,
            'period' => '202612',
            'outstanding_balance' => $outstanding,
            'collectibility' => 4,
        ]);
        CkpnPeriodClassification::factory()->create([
            'financing_account_id' => $account->id,
            'period' => '202612',
            'outstanding_balance' => $outstanding,
            'collectibility' => 4,
            'classification' => 'individual',
            'is_top_n_outstanding' => $outstanding >= 300_000_000,
        ]);
    }

    $calculator = new CkpnIndividualCalculator($n, 0.05);
    $results = $calculator->calculatePerAccount(UsageType::ModalKerja, '202612');

    $topNResults = array_filter($results, fn ($r) => $r['is_top_n_outstanding']);
    expect(count($topNResults))->toBe($n);

    $topNOutstandings = array_map(fn ($r) => $r['outstanding_balance'], $topNResults);
    expect(in_array(500_000_000.0, $topNOutstandings))->toBeTrue();
    expect(in_array(300_000_000.0, $topNOutstandings))->toBeTrue();
});
