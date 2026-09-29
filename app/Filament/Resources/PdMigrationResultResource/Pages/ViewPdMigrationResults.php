<?php

declare(strict_types=1);

namespace App\Filament\Resources\PdMigrationResultResource\Pages;

use App\Filament\Resources\PdMigrationResultResource;
use Filament\Resources\Pages\ViewRecords;

class ViewPdMigrationResults extends ViewRecords
{
    protected static string $resource = PdMigrationResultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
