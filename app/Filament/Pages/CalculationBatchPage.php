<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Actions\BatchCalculationAction;
use Filament\Pages\Page;

class CalculationBatchPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Batch Calculation';

    protected static string $view = 'filament.pages.calculation-batch-page';

    protected static ?int $navigationSort = 10;

    protected function getHeaderActions(): array
    {
        return [
            BatchCalculationAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Jalankan Batch Perhitungan';
    }
}
