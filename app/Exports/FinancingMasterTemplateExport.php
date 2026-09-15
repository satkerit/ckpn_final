<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Excel untuk upload master data pembiayaan (financing_accounts).
 * Ref: PRD Bab 15
 */
class FinancingMasterTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new FinancingMasterDataSheet,
            new FinancingMasterPetunjukSheet,
        ];
    }
}
