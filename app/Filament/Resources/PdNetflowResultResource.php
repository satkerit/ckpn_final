<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Filament\Actions\ExportSnapshotAction;
use App\Models\PdNetflowResult;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecords;
use Illuminate\Database\Eloquent\Builder;

class PdNetflowResultResource extends Resource
{
    protected static ?string $model = PdNetflowResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-line';

    protected static ?string $navigationLabel = 'PD Netflow';

    protected static ?string $pluralModelLabel = 'PD Netflow Results';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('calculation_period')
                    ->label('Periode Kalkulasi')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('usage_type')
                    ->label('Jenis Penggunaan')
                    ->formatStateUsing(fn (UsageType $state) => $state->label())
                    ->badge()
                    ->color(fn (UsageType $state) => match ($state) {
                        UsageType::ModalKerja => 'blue',
                        UsageType::Investasi => 'green',
                        UsageType::Konsumsi => 'amber',
                    }),

                TextColumn::make('office_code')
                    ->label('Kode Kantor')
                    ->formatStateUsing(fn (?string $state) => $state ?? '(Global)')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('akad_code')
                    ->label('Kode Akad')
                    ->formatStateUsing(fn (?string $state) => $state ?? '(Global)')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('from_bucket_id')
                    ->label('Bucket')
                    ->formatStateUsing(fn (int $state) => "Bucket {$state}")
                    ->sortable(),

                TextColumn::make('pd_rate')
                    ->label('PD Rate')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%')
                    ->sortable(),

                TextColumn::make('window_months')
                    ->label('Window (Bln)')
                    ->sortable(),

                TextColumn::make('calculationRunLog.status')
                    ->label('Job Status')
                    ->formatStateUsing(fn (RunStatus $state) => $state->name)
                    ->badge()
                    ->color(fn (RunStatus $state) => match ($state) {
                        RunStatus::Completed => 'success',
                        RunStatus::Processing => 'info',
                        RunStatus::Pending => 'warning',
                        RunStatus::Failed => 'danger',
                        RunStatus::Approved => 'success',
                    }),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('calculation_period')
                    ->label('Periode')
                    ->options(fn () => self::availablePeriods()),

                SelectFilter::make('usage_type')
                    ->label('Jenis Penggunaan')
                    ->options(collect(UsageType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),

                SelectFilter::make('from_bucket_id')
                    ->label('Bucket')
                    ->relationship('bucket', 'label')
                    ->multiple(),

                Filter::make('has_office_code')
                    ->label('Segmentasi Kantor')
                    ->toggle()
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereNotNull('office_code') : $query->whereNull('office_code')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportSnapshotAction::makeForPdNetflow(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Perhitungan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('calculation_period')
                            ->label('Periode Kalkulasi'),

                        TextEntry::make('usage_type')
                            ->label('Jenis Penggunaan')
                            ->formatStateUsing(fn (UsageType $state) => $state->label()),

                        TextEntry::make('office_code')
                            ->label('Kode Kantor')
                            ->formatStateUsing(fn (?string $state) => $state ?? '(Global - Konsolidasi)'),

                        TextEntry::make('akad_code')
                            ->label('Kode Akad')
                            ->formatStateUsing(fn (?string $state) => $state ?? '(Global - Konsolidasi)'),

                        TextEntry::make('bucket.label')
                            ->label('Bucket'),

                        TextEntry::make('window_months')
                            ->label('Rolling Window')
                            ->formatStateUsing(fn (int $state) => "{$state} bulan"),

                        TextEntry::make('data_period_start')
                            ->label('Periode Data Mulai'),

                        TextEntry::make('data_period_end')
                            ->label('Periode Data Akhir'),
                    ]),

                Section::make('Hasil Kalkulasi')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pd_rate')
                            ->label('PD Rate')
                            ->formatStateUsing(fn (string $state) => number_format((float) $state, 8)),

                        TextEntry::make('account_count')
                            ->label('Jumlah Akun')
                            ->formatStateUsing(fn (int $state) => number_format($state, 0, ',', '.')),

                        TextEntry::make('total_outstanding')
                            ->label('Total Outstanding')
                            ->formatStateUsing(fn (string $state) => 'Rp ' . number_format((float) $state, 2, ',', '.')),

                        TextEntry::make('defaulted_amount')
                            ->label('Jumlah Default')
                            ->formatStateUsing(fn (string $state) => 'Rp ' . number_format((float) $state, 2, ',', '.')),
                    ]),

                Section::make('Catatan & Audit')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Catatan Perhitungan')
                            ->markdown()
                            ->columnSpanFull(),

                        TextEntry::make('calculationRunLog.error_message')
                            ->label('Error (jika ada)')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?string $state) => $state ?? '—'),

                        TextEntry::make('calculationRunLog.started_at')
                            ->label('Mulai Diproses')
                            ->dateTime('Y-m-d H:i:s'),

                        TextEntry::make('calculationRunLog.completed_at')
                            ->label('Selesai')
                            ->dateTime('Y-m-d H:i:s'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPdNetflowResults::route('/'),
            'view' => Pages\ViewPdNetflowResults::route('/{record}'),
        ];
    }

    protected static function availablePeriods(): array
    {
        return PdNetflowResult::query()
            ->distinct('calculation_period')
            ->pluck('calculation_period')
            ->mapWithKeys(fn ($period) => [$period => $period])
            ->toArray();
    }
}
