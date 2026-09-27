<?php

namespace App\Filament\Resources\RiskSegment\Pages;

use App\Filament\Resources\RiskSegment\RiskSegmentResource;
use Filament\Pages\Page;

class ListRiskSegments extends Page
{
    protected static string $resource = RiskSegmentResource::class;

    protected static string $view = 'filament.resources.risk-segment.pages.list-risk-segments';
}
