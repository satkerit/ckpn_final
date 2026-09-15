<?php

declare(strict_types=1);

use App\Domain\Ckpn\Lgd\CollateralShortfall\LgdCollateralShortfallCalculator;

test('shortfall is outstanding minus collateral net value', function () {
    $outstanding = 100_000_000.0;
    $collateralNetValue = 75_000_000.0;
    $shortfall = max(0.0, $outstanding - $collateralNetValue);
    expect($shortfall)->toBe(25_000_000.0);
});

test('lgd cs rate is shortfall divided by outstanding', function () {
    $outstanding = 100_000_000.0;
    $shortfall = 25_000_000.0;
    $lgd = min(1.0, $shortfall / $outstanding);
    expect($lgd)->toBe(0.25);
});

test('lgd cs rate is 0 when collateral covers full outstanding', function () {
    $outstanding = 100_000_000.0;
    $collateralNetValue = 120_000_000.0;
    $shortfall = max(0.0, $outstanding - $collateralNetValue);
    $lgd = $shortfall / $outstanding;
    expect($lgd)->toBe(0.0);
});

test('lgd cs rate is capped at 1.0', function () {
    $outstanding = 100_000_000.0;
    $collateralNetValue = 0.0;
    $shortfall = max(0.0, $outstanding - $collateralNetValue);
    $lgd = min(1.0, $shortfall / $outstanding);
    expect($lgd)->toBe(1.0);
});

test('aggregate menghitung total dan rata-rata per segmen', function () {
    $aggregate = (new LgdCollateralShortfallCalculator)->aggregate([
        ['outstanding_balance' => 100_000_000.0, 'collateral_net_value' => 75_000_000.0, 'shortfall' => 25_000_000.0, 'lgd_rate' => 0.25],
        ['outstanding_balance' => 200_000_000.0, 'collateral_net_value' => 100_000_000.0, 'shortfall' => 100_000_000.0, 'lgd_rate' => 0.5],
    ]);

    expect($aggregate['account_count'])->toBe(2)
        ->and($aggregate['total_outstanding'])->toBe(300_000_000.0)
        ->and($aggregate['total_collateral_net_value'])->toBe(175_000_000.0)
        ->and($aggregate['total_shortfall'])->toBe(125_000_000.0)
        ->and($aggregate['avg_lgd_rate'])->toBe(0.375);
});

test('aggregate segmen tanpa akun menghasilkan nol', function () {
    $aggregate = (new LgdCollateralShortfallCalculator)->aggregate([]);

    expect($aggregate['account_count'])->toBe(0)
        ->and($aggregate['total_outstanding'])->toBe(0.0)
        ->and($aggregate['total_collateral_net_value'])->toBe(0.0)
        ->and($aggregate['total_shortfall'])->toBe(0.0)
        ->and($aggregate['avg_lgd_rate'])->toBe(0.0);
});
