<?php

declare(strict_types=1);

use App\Domain\Ckpn\Services\RollingWindowResolver;

// Confirmed values from PRD Bab 7.1: window=36, forward=6, calculationPeriod=202612
// outstanding: 202212 s/d 202512
// rate:        202301 s/d 202612 (202601-202612 = proyeksi)
// lookback:    202507-202512
// compound:    202301-202512

test('resolver returns correct outstanding start period', function () {
    $resolver = new RollingWindowResolver(36, 6);
    expect($resolver->outstandingStartPeriod('202612'))->toBe('202212');
});

test('resolver returns correct rate start period', function () {
    $resolver = new RollingWindowResolver(36, 6);
    expect($resolver->rateStartPeriod('202612'))->toBe('202301');
});

test('resolver returns correct projection start period', function () {
    $resolver = new RollingWindowResolver(36, 6);
    expect($resolver->projectionStartPeriod('202612'))->toBe('202601');
});

test('resolver returns correct projection lookback start', function () {
    $resolver = new RollingWindowResolver(36, 6);
    expect($resolver->projectionLookbackStart('202612'))->toBe('202507');
});

test('resolver returns correct compound flow end period', function () {
    $resolver = new RollingWindowResolver(36, 6);
    expect($resolver->compoundFlowEndPeriod('202612'))->toBe('202512');
});

test('resolver returns correct outstanding end period', function () {
    $resolver = new RollingWindowResolver(36, 6);
    expect($resolver->outstandingEndPeriod('202612'))->toBe('202512');
});
