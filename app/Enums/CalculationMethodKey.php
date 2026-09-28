<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Metode perhitungan yang memiliki rentang data (rolling window / proyeksi / matriks).
 * Ref: PRD Bab 7, 8, 9, 10.
 */
enum CalculationMethodKey: string
{
    case PdNetflow = 'pd_netflow';
    case PdMigration = 'pd_migration';
    case LgdExpectedRecoveries = 'lgd_expected_recoveries';
    case LgdCollateralShortfall = 'lgd_collateral_shortfall';

    public function label(): string
    {
        return match ($this) {
            self::PdNetflow => 'PD Netflow',
            self::PdMigration => 'PD Migration',
            self::LgdExpectedRecoveries => 'LGD Expected Recoveries',
            self::LgdCollateralShortfall => 'LGD Collateral Shortfall',
        };
    }
}
