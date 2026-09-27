<?php

use App\Models\DataQualityAnomaly;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

?>

<x-filament-panels::page>
    <x-filament-tables::table
        :table="$table"
        :records="$this->getTableRecords()"
    >
        <x-slot name="header">
            <x-filament-tables\Header\Header>
                <x-slot name="heading">
                    {{ __('Data Quality Anomalies') }}
                </x-slot>
            </x-filament-tables\Header\Header>
        </x-slot>

        <x-slot name="columns">
            @foreach ($table->getColumns() as $column)
                {{ $column->view($column, get_class($this)) }}
            @endforeach
        </x-slot>

        <x-slot name="actions">
            @foreach ($table->getActions() as $action)
                {{ $action->view($action, get_class($this)) }}
            @endforeach
        </x-slot>

        <x-slot name="bulkActions">
            @foreach ($table->getBulkActions() as $action)
                {{ $action->view($action, get_class($this)) }}
            @endforeach
        </x-slot>

        <x-slot name="emptyState">
            <x-filament-tables\EmptyState\EmptyState
                :heading="__('No data quality anomalies found')"
                :icon="'heroicon-o-check-circle'"
                :description="__('There are no anomalies to display.')"
            />
        </x-slot>
    </x-filament-tables\table>
</x-filament-panels\page>