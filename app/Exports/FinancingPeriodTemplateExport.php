<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Excel untuk upload data historis pembiayaan per periode.
 * Ref: PRD Bab 15 — financing_account_periods
 */
class FinancingPeriodTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new FinancingPeriodDataSheet,
            new FinancingPeriodPetunjukSheet,
        ];
    }
}
