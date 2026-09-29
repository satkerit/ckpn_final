<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\CalculationSegmentationConfig;
use App\Filament\Actions\DispatchPdNetflowCalculationAction;
use App\Filament\Actions\DispatchPdMigrationCalculationAction;
use App\Filament\Actions\DispatchLgdCalculationAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CalculationSegmentationConfigResource extends Resource
{
    protected static ?string $model = CalculationSegmentationConfig::class;

    protected static ?string $slug = 'calculation-segmentation-configs';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('method')
                    ->options([
                        'pd_netflow' => 'PD Netflow',
                        'pd_migration' => 'PD Migration',
                        'lgd_er' => 'LGD Expected Recoveries',
                        'lgd_cs' => 'LGD Collateral Shortfall',
                        'ckpn_individual' => 'CKPN Individual',
                        'ckpn_collective' => 'CKPN Collective',
                    ])
                    ->required()
                    ->disabled(fn (?CalculationSegmentationConfig $record) => $record !== null),

                CheckboxList::make('segment_dimensions')
                    ->label('Segment Dimensions')
                    ->options([
                        'office_code' => 'Office Code',
                        'akad_code' => 'Akad Code',
                        'usage_type' => 'Usage Type',
                    ])
                    ->default([])
                    ->helperText('Pilih dimensi yang ingin disegmentasi. Kosong = no segmentation.'),

                Toggle::make('is_active')
                    ->default(true),

                Textarea::make('notes')
                    ->maxLength(500),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('method')->searchable(),
                TextColumn::make('segment_dimensions')
                    ->formatStateUsing(fn ($state) => implode(', ', $state ?? [])),
                ToggleColumn::make('is_active'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->headerActions([
                DispatchPdNetflowCalculationAction::make(),
                DispatchPdMigrationCalculationAction::make(),
                DispatchLgdCalculationAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
