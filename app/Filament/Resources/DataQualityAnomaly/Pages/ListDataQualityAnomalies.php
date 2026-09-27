<?php

namespace App\Filament\Resources\DataQualityAnomaly\Pages;

use App\Filament\Resources\DataQualityAnomaly\DataQualityAnomalyResource;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListDataQualityAnomalies extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = DataQualityAnomalyResource::class;

    protected static string $view = 'filament.resources.data-quality-anomaly.pages.list-data-quality-anomalies';

    protected function getTableColumns(): array
    {
        return DataQualityAnomalyResource::table($this->getTable())->getColumns();
    }

    protected function getTableFilters(): array
    {
        return DataQualityAnomalyResource::table($this->getTable())->getFilters();
    }

    protected function getTableActions(): array
    {
        return DataQualityAnomalyResource::table($this->getTable())->getActions();
    }

    protected function getTableBulkActions(): array
    {
        return DataQualityAnomalyResource::table($this->getTable())->getBulkActions();
    }

    protected function getTableQuery(): Builder
    {
        return DataQualityAnomaly::query()->with(['bucket']);
    }

    public function table(Table $table): Table
    {
        return DataQualityAnomalyResource::table($table)
            ->query($this->getTableQuery());
    }
}
