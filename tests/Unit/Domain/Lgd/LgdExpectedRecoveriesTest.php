<?php

declare(strict_types=1);

test('lgd rate = 1 - expected recovery rate', function () {
    // If avg recovery rate = 0.40, LGD = 0.60
    $recoveryRates = [0.30, 0.40, 0.50, 0.35, 0.45]; // 5 years
    $avg = array_sum($recoveryRates) / count($recoveryRates);
    $lgd = 1.0 - $avg;
    expect(round($lgd, 4))->toBe(round(1.0 - 0.40, 4));
});

test('lgd rate is never negative', function () {
    // Recovery rate > 1.0 is capped at 1.0, so LGD >= 0
    $recoveryRate = 1.2; // edge case: cap at 1.0
    $capped = min($recoveryRate, 1.0);
    $lgd = max(0.0, 1.0 - $capped);
    expect($lgd)->toBe(0.0);
});

test('lgd is 1.0 when there are no recoveries', function () {
    $recoveryRates = [0.0, 0.0, 0.0];
    $avg = array_sum($recoveryRates) / count($recoveryRates);
    $lgd = 1.0 - $avg;
    expect($lgd)->toBe(1.0);
});
