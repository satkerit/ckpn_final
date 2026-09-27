<?php

namespace App\Filament\Resources\RiskSegment\Pages;

use App\Filament\Resources\RiskSegment\RiskSegmentResource;
use App\Filament\Traits\HasConfirmationDialogs;
use Filament\Resources\Pages\EditRecord;

class EditRiskSegment extends EditRecord
{
    use HasConfirmationDialogs;

    protected static string $resource = RiskSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return $this->getEditHeaderActions('segmen risiko');
    }

    protected function getFormActions(): array
    {
        return $this->getEditFormActions('segmen risiko');
    }
}
