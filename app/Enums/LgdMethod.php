<?php

declare(strict_types=1);

namespace App\Enums;

enum LgdMethod: string
{
    case ExpectedRecoveries = 'expected_recoveries';
    case CollateralShortfall = 'collateral_shortfall';

    public function label(): string
    {
        return match ($this) {
            self::ExpectedRecoveries => 'Expected Recoveries',
            self::CollateralShortfall => 'Collateral Shortfall',
        };
    }
}
