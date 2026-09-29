<?php

declare(strict_types=1);

namespace App\Filament\Resources\LgdCollateralShortfallResultResource\Pages;

use App\Filament\Resources\LgdCollateralShortfallResultResource;
use Filament\Resources\Pages\ListRecords;

class ListLgdCollateralShortfallResults extends ListRecords
{
    protected static string $resource = LgdCollateralShortfallResultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
