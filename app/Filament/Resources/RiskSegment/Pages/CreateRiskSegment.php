<?php

namespace App\Filament\Resources\RiskSegment\Pages;

use App\Filament\Resources\RiskSegment\RiskSegmentResource;
use App\Filament\Traits\HasConfirmationDialogs;
use Filament\Resources\Pages\CreateRecord;

class CreateRiskSegment extends CreateRecord
{
    use HasConfirmationDialogs;

    protected static string $resource = RiskSegmentResource::class;

    protected function getFormActions(): array
    {
        return $this->getCreateFormActions('segmen risiko');
    }
}
