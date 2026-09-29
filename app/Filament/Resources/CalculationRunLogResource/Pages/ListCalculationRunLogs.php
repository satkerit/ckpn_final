<?php

declare(strict_types=1);

namespace App\Filament\Resources\CalculationRunLogResource\Pages;

use App\Filament\Resources\CalculationRunLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCalculationRunLogs extends ListRecords
{
    protected static string $resource = CalculationRunLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
