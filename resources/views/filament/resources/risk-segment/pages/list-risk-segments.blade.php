<?php

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\RiskSegment;

?>

<x-filament-panels::page>
    <x-filament-tables::table
        :table="$table"
        :records="$this->getTableRecords()"
    >
        <x-slot name="header">
            <x-filament-tables\Header\Header>
                <x-slot name="heading">
                    {{ __('Risk Segments') }}
                </x-slot>
                <x-slot name="actions">
                    <x-filament\actions\Action::make('create')
                        ->label('Tambah Segmen')
                        ->icon('heroicon-o-plus')
                        ->url(route('filament.resources.risk-segment.create'))
                    />
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
                :heading="__('No risk segments found')"
                :icon="'heroicon-o-squares-2x2'"
                :description="__('Create a risk segment to organize your portfolio.')"
            />
        </x-slot>
    </x-filament-tables\table>
</x-filament-panels\page>