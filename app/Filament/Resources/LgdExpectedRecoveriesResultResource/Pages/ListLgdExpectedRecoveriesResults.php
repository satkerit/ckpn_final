<?php

declare(strict_types=1);

namespace App\Filament\Resources\LgdExpectedRecoveriesResultResource\Pages;

use App\Filament\Resources\LgdExpectedRecoveriesResultResource;
use Filament\Resources\Pages\ListRecords;

class ListLgdExpectedRecoveriesResults extends ListRecords
{
    protected static string $resource = LgdExpectedRecoveriesResultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
