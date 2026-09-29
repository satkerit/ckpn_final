<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Filament\Actions\ExportSnapshotAction;
use App\Models\LgdExpectedRecoveriesResult;
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

class LgdExpectedRecoveriesResultResource extends Resource
{
    protected static ?string $model = LgdExpectedRecoveriesResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'LGD Expected Recoveries';

    protected static ?string $pluralModelLabel = 'LGD Expected Recoveries Results';

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

                TextColumn::make('lgd_rate')
                    ->label('LGD Rate')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%')
                    ->sortable(),

                TextColumn::make('expected_recovery_rate')
                    ->label('Recovery Rate')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%')
                    ->sortable(),

                TextColumn::make('window_years')
                    ->label('Window (Thn)')
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
                    ExportSnapshotAction::makeForLgdEr(),
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

                        TextEntry::make('window_years')
                            ->label('Rolling Window')
                            ->formatStateUsing(fn (int $state) => "{$state} tahun"),

                        TextEntry::make('data_period_start')
                            ->label('Periode Data Mulai'),

                        TextEntry::make('data_period_end')
                            ->label('Periode Data Akhir'),

                        TextEntry::make('is_all_account')
                            ->label('Fallback All-Account')
                            ->formatStateUsing(fn (bool $state) => $state ? 'Ya (segmen kosong)' : 'Tidak'),
                    ]),

                Section::make('Hasil Kalkulasi')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lgd_rate')
                            ->label('LGD Rate')
                            ->formatStateUsing(fn (string $state) => number_format((float) $state, 8)),

                        TextEntry::make('expected_recovery_rate')
                            ->label('Expected Recovery Rate')
                            ->formatStateUsing(fn (string $state) => number_format((float) $state, 8)),

                        TextEntry::make('total_writeoff_amount')
                            ->label('Total Writeoff')
                            ->formatStateUsing(fn (string $state) => 'Rp ' . number_format((float) $state, 2, ',', '.')),

                        TextEntry::make('total_recovery_amount')
                            ->label('Total Recovery')
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
            'index' => Pages\ListLgdExpectedRecoveriesResults::route('/'),
            'view' => Pages\ViewLgdExpectedRecoveriesResults::route('/{record}'),
        ];
    }

    private static function availablePeriods(): array
    {
        $periods = [];
        $today = now();
        for ($i = 0; $i < 24; $i++) {
            $period = $today->copy()->subMonths($i)->format('Ym');
            $periods[$period] = $period;
        }
        return $periods;
    }
}
