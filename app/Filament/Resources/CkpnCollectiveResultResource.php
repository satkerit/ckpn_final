<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CkpnCollectiveResultResource\Pages;
use App\Models\CkpnCollectiveResult;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Filament Resource: CKPN Collective Results (read-only).
 *
 * Ref: PRD Bab 11, 17
 */
final class CkpnCollectiveResultResource extends Resource
{
    protected static ?string $model = CkpnCollectiveResult::class;

    protected static ?string $slug = 'ckpn-collective-results';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'CKPN Collective Results';

    protected static ?string $navigationGroup = 'CKPN';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('calculation_period')
                    ->disabled()
                    ->label('Period (YYYYMM)'),
                TextInput::make('usage_type')
                    ->disabled(),
                TextInput::make('pd_rate')
                    ->disabled()
                    ->step(0.000001),
                TextInput::make('lgd_rate')
                    ->disabled()
                    ->step(0.000001),
                TextInput::make('ead')
                    ->disabled()
                    ->step(0.01),
                TextInput::make('ckpn_amount')
                    ->disabled()
                    ->step(0.01),
                TextInput::make('pd_method_used')
                    ->disabled(),
                TextInput::make('lgd_method_used')
                    ->disabled(),
                TextInput::make('notes')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calculation_period')
                    ->label('Period')
                    ->sortable(),
                BadgeColumn::make('usage_type')
                    ->sortable(),
                TextColumn::make('office_code')
                    ->sortable(),
                TextColumn::make('financing_account.account_number')
                    ->label('Account')
                    ->sortable(),
                TextColumn::make('pd_rate')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 6))
                    ->sortable(),
                TextColumn::make('lgd_rate')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 6))
                    ->sortable(),
                TextColumn::make('ead')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->sortable(),
                TextColumn::make('ckpn_amount')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->sortable(),
                TextColumn::make('calculationRunLog.status')
                    ->label('Job Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('calculation_period')
                    ->relationship('calculationRunLog', 'period')
                    ->searchable(),
                SelectFilter::make('usage_type'),
                SelectFilter::make('office_code')
                    ->options(
                        \App\Models\FinancingOffice::pluck('name', 'code')->toArray()
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCkpnCollectiveResults::route('/'),
            'view' => Pages\ViewCkpnCollectiveResults::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(CkpnCollectiveResult $record): bool
    {
        return false;
    }

    public static function canDelete(CkpnCollectiveResult $record): bool
    {
        return false;
    }
}
