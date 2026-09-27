<?php

namespace App\Filament\Resources\DataQualityAnomaly\Pages;

use App\Filament\Resources\DataQualityAnomaly\DataQualityAnomalyResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextArea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\ViewRecord;

class ViewDataQualityAnomaly extends ViewRecord
{
    protected static string $resource = DataQualityAnomalyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReviewed')
                ->label('Tandai Ditinjau')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status === 'pending')
                ->action(function (): void {
                    $this->getRecord()->status = 'reviewed';
                    $this->getRecord()->reviewed_by = auth()->id();
                    $this->getRecord()->reviewed_at = now();
                    $this->getRecord()->save();
                    $this->refresh();
                }),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Informasi Anomali')
                ->schema([
                    TextInput::make('period')
                        ->label('Periode')
                        ->disabled(),
                    TextInput::make('anomaly_type')
                        ->label('Tipe Anomali')
                        ->disabled(),
                    TextInput::make('severity')
                        ->label('Tingkat Keparahan')
                        ->disabled(),
                    TextArea::make('description')
                        ->label('Deskripsi')
                        ->rows(4)
                        ->disabled(),
                ])->columns(2),

            Section::make('Detail Teknis')
                ->schema([
                    TextInput::make('bucket.label')
                        ->label('Bucket')
                        ->disabled(),
                    TextInput::make('usage_type')
                        ->label('Segmen Penggunaan')
                        ->disabled(),
                    TextInput::make('source_table')
                        ->label('Tabel Sumber')
                        ->disabled(),
                ])->columns(3),

            Section::make('Status Tinjauan')
                ->schema([
                    TextInput::make('status')
                        ->label('Status')
                        ->disabled(),
                    TextInput::make('reviewed_by')
                        ->label('Ditinjau Oleh')
                        ->disabled(),
                    TextInput::make('reviewed_at')
                        ->label('Tanggal Tinjauan')
                        ->disabled(),
                    TextArea::make('review_notes')
                        ->label('Catatan Tinjauan')
                        ->rows(3)
                        ->disabled(),
                ])->columns(2),
        ];
    }
}
