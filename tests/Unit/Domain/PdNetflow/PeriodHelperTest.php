<?php

declare(strict_types=1);

use App\Domain\Ckpn\Services\PeriodHelper;

test('shiftBack decrements month correctly', function () {
    expect(PeriodHelper::shiftBack('202612', 1))->toBe('202611');
    expect(PeriodHelper::shiftBack('202601', 1))->toBe('202512');
    expect(PeriodHelper::shiftBack('202612', 12))->toBe('202512');
    expect(PeriodHelper::shiftBack('202612', 36))->toBe('202312');
    expect(PeriodHelper::shiftBack('202612', 48))->toBe('202212');
});

test('shiftForward increments month correctly', function () {
    expect(PeriodHelper::shiftForward('202611', 1))->toBe('202612');
    expect(PeriodHelper::shiftForward('202512', 1))->toBe('202601');
});

test('range returns inclusive list', function () {
    $result = PeriodHelper::range('202301', '202303');
    expect($result)->toBe(['202301', '202302', '202303']);
});

test('diffMonths calculates correctly', function () {
    expect(PeriodHelper::diffMonths('202212', '202612'))->toBe(48);
    expect(PeriodHelper::diffMonths('202301', '202512'))->toBe(35);
});
