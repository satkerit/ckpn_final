<?php

namespace App\Filament\Resources\RiskSegment;

use App\Filament\Resources\RiskSegment\Pages\CreateRiskSegment;
use App\Filament\Resources\RiskSegment\Pages\EditRiskSegment;
use App\Filament\Resources\RiskSegment\Pages\ListRiskSegments;
use App\Filament\Traits\HasConfirmationDialogs;
use App\Models\RiskSegment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RiskSegmentResource extends Resource
{
    use HasConfirmationDialogs;

    protected static ?string $model = RiskSegment::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Segmen Risiko';

    protected static ?string $pluralModelLabel = 'Segmen Risiko';

    protected static ?string $modelLabel = 'Segmen Risiko';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Segmen')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Segmen')
                            ->required()
                            ->unique('risk_segments', 'code', ignoreRecord: true)
                            ->maxLength(20),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Segmen')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(500),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Segmen')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Tidak Aktif',
                    ]),
            ])
            ->actions(static::getStandardTableActions('segmen risiko'))
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make(
                    static::getStandardBulkActions('segmen risiko')
                ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRiskSegments::route('/'),
            'create' => CreateRiskSegment::route('/create'),
            'edit' => EditRiskSegment::route('/{record}/edit'),
        ];
    }
}
