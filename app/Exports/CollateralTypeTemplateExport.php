<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Excel untuk upload master data jenis jaminan.
 * Ref: PRD Bab 10 & 15 — collateral_types
 */
class CollateralTypeTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new CollateralTypeDataSheet,
            new CollateralTypePetunjukSheet,
        ];
    }
}
