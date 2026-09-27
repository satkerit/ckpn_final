<?php

namespace App\Filament\Resources\DataQualityAnomaly\Resources;

use App\Filament\Resources\DataQualityAnomaly\Pages\ListDataQualityAnomalies;
use App\Filament\Resources\DataQualityAnomaly\Pages\ViewDataQualityAnomaly;
use App\Models\DataQualityAnomaly;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DataQualityAnomalyResource extends Resource
{
    protected static ?string $model = DataQualityAnomaly::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Anomali Data Quality';

    protected static ?string $pluralModelLabel = 'Anomali Data Quality';

    protected static ?string $modelLabel = 'Anomali Data';

    protected static ?string $navigationGroup = 'Data Quality';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Periode')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('usageTypeLabel')
                    ->label('Segmen')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bucket.label')
                    ->label('Bucket')
                    ->sortable(),
                Tables\Columns\TextColumn::make('anomaly_type')
                    ->label('Tipe Anomali')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\TextColumn::make('severity')
                    ->label('Tingkat')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'reviewed' => 'success',
                        'resolved' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reviewed_by')
                    ->label('Ditinjau Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('period', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('period')
                    ->label('Periode'),
                Tables\Filters\SelectFilter::make('anomaly_type')
                    ->label('Tipe Anomali'),
                Tables\Filters\SelectFilter::make('severity')
                    ->label('Tingkat'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markReviewed')
                    ->label('Tandai Ditinjau')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (DataQualityAnomaly $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Anomali Sebagai Ditinjau')
                    ->modalDescription('Apakah Anda yakin sudah meninjau anomali ini dan ingin menandainya sebagai "Ditinjau"?')
                    ->modalSubmitActionLabel('Ya, Tandai Ditinjau')
                    ->modalCancelActionLabel('Batal')
                    ->action(function (DataQualityAnomaly $record): void {
                        $record->status = 'reviewed';
                        $record->reviewed_by = auth()->id();
                        $record->reviewed_at = now();
                        $record->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markReviewed')
                        ->label('Tandai Ditinjau')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Tandai Anomali Terpilih Sebagai Ditinjau')
                        ->modalDescription('Apakah Anda yakin ingin menandai semua anomali terpilih sebagai "Ditinjau"?')
                        ->modalSubmitActionLabel('Ya, Tandai Semua')
                        ->modalCancelActionLabel('Batal')
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    $record->status = 'reviewed';
                                    $record->reviewed_by = auth()->id();
                                    $record->reviewed_at = now();
                                    $record->save();
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataQualityAnomalies::route('/'),
            'view' => ViewDataQualityAnomaly::route('/{record}'),
        ];
    }
}
