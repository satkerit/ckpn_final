<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Excel untuk upload master data kantor pembiayaan.
 * Ref: PRD Bab 15 — financing_offices
 */
class FinancingOfficeTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new FinancingOfficeDataSheet,
            new FinancingOfficePetunjukSheet,
        ];
    }
}
