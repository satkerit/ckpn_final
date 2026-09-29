<?php

declare(strict_types=1);

namespace App\Filament\Resources\PdNetflowResultResource\Pages;

use App\Filament\Resources\PdNetflowResultResource;
use Filament\Resources\Pages\ListRecords;

class ListPdNetflowResults extends ListRecords
{
    protected static string $resource = PdNetflowResultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
