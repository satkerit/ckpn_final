<?php

declare(strict_types=1);

namespace App\Filament\Resources\CkpnCollectiveResultResource\Pages;

use App\Filament\Resources\CkpnCollectiveResultResource;
use Filament\Resources\Pages\ListRecords;

final class ListCkpnCollectiveResults extends ListRecords
{
    protected static string $resource = CkpnCollectiveResultResource::class;
}
