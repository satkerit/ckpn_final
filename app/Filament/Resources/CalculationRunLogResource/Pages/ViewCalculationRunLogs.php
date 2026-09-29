<?php

declare(strict_types=1);

namespace App\Filament\Resources\CalculationRunLogResource\Pages;

use App\Enums\RunStatus;
use App\Filament\Actions\ApproveCalculationAction;
use App\Filament\Resources\CalculationRunLogResource;
use Filament\Resources\Pages\ViewRecords;

class ViewCalculationRunLogs extends ViewRecords
{
    protected static string $resource = CalculationRunLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApproveCalculationAction::make()
                ->visible(fn ($record) => $record->status === RunStatus::Completed && $record->status !== RunStatus::Approved),
        ];
    }
}
