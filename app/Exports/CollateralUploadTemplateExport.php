<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Excel untuk upload data jaminan (collaterals).
 * Ref: PRD Bab 10 & 15
 */
class CollateralUploadTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new CollateralUploadDataSheet,
            new CollateralUploadPetunjukSheet,
        ];
    }
}
