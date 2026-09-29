<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\CalculationRunLog;
use Filament\Forms\Components\DateTimePickerField;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CalculationRunLogResource extends Resource
{
    protected static ?string $model = CalculationRunLog::class;

    protected static ?string $slug = 'calculation-run-logs';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Job Status';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('period')
                    ->label('Periode')
                    ->disabled(),

                Select::make('run_type')
                    ->label('Metode Kalkulasi')
                    ->disabled(),

                Select::make('usage_type')
                    ->label('Jenis Penggunaan')
                    ->disabled(),

                Select::make('status')
                    ->label('Status')
                    ->disabled(),

                DateTimePickerField::make('started_at')
                    ->label('Mulai')
                    ->disabled(),

                DateTimePickerField::make('completed_at')
                    ->label('Selesai')
                    ->disabled(),

                Textarea::make('notes')
                    ->label('Catatan')
                    ->disabled()
                    ->rows(5),

                Textarea::make('error_message')
                    ->label('Pesan Error')
                    ->disabled()
                    ->rows(5)
                    ->visible(fn ($record) => $record?->error_message),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period')
                    ->label('Periode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('run_type')
                    ->label('Metode')
                    ->badge(),

                TextColumn::make('usage_type')
                    ->label('Jenis')
                    ->badge(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'danger' => 'Failed',
                        'warning' => 'Pending',
                        'info' => 'Processing',
                        'success' => 'Completed',
                        'secondary' => 'Approved',
                    ])
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Processing' => 'Processing',
                        'Completed' => 'Completed',
                        'Failed' => 'Failed',
                        'Approved' => 'Approved',
                    ]),

                SelectFilter::make('run_type')
                    ->relationship('run_type', 'label', fn ($query) => $query),

                SelectFilter::make('period')
                    ->options(fn () => CalculationRunLog::pluck('period', 'period')->unique()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
