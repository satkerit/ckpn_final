<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CkpnIndividualResult;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecords;

class CkpnIndividualResultResource extends Resource
{
    protected static ?string $model = CkpnIndividualResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';

    protected static ?string $navigationLabel = 'CKPN Individual';

    protected static ?string $pluralModelLabel = 'CKPN Individual Results';

    protected static bool $shouldRegisterNavigation = false;

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('calculation_period')
                    ->label('Periode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('account_number')
                    ->label('Nomor Akun')
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
                    ->label('Kantor')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('akad_code')
                    ->label('Akad')
                    ->sortable(),

                TextColumn::make('bucket')
                    ->label('Bucket')
                    ->sortable(),

                TextColumn::make('days_past_due')
                    ->label('Days Past Due')
                    ->sortable(),

                TextColumn::make('pd_rate')
                    ->label('PD Rate')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%'),

                TextColumn::make('lgd_rate')
                    ->label('LGD Rate')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%'),

                TextColumn::make('ckpn_rate')
                    ->label('CKPN Rate')
                    ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%'),

                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->formatStateUsing(fn (string $state) => 'Rp ' . number_format((float) $state, 2, ',', '.'))
                    ->alignment('right'),

                TextColumn::make('ckpn_amount')
                    ->label('CKPN Amount')
                    ->formatStateUsing(fn (string $state) => 'Rp ' . number_format((float) $state, 2, ',', '.'))
                    ->alignment('right'),

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
                    ->dateTime('Y-m-d H:i:s')
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
            ])
            ->paginated([25, 50, 100]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detail Kalkulasi')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('calculation_period')
                            ->label('Periode Kalkulasi'),

                        TextEntry::make('account_number')
                            ->label('Nomor Akun'),

                        TextEntry::make('usage_type')
                            ->label('Jenis Penggunaan')
                            ->formatStateUsing(fn (UsageType $state) => $state->label()),

                        TextEntry::make('office_code')
                            ->label('Kode Kantor'),

                        TextEntry::make('akad_code')
                            ->label('Kode Akad'),

                        TextEntry::make('bucket')
                            ->label('Risk Bucket'),

                        TextEntry::make('days_past_due')
                            ->label('Days Past Due'),

                        TextEntry::make('outstanding')
                            ->label('Outstanding Balance')
                            ->formatStateUsing(fn (string $state) => 'Rp ' . number_format((float) $state, 2, ',', '.')),
                    ]),

                Section::make('Rate & Hasil')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('pd_rate')
                            ->label('PD Rate')
                            ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%'),

                        TextEntry::make('lgd_rate')
                            ->label('LGD Rate')
                            ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%'),

                        TextEntry::make('ckpn_rate')
                            ->label('CKPN Rate')
                            ->formatStateUsing(fn (string $state) => number_format((float) $state * 100, 4) . '%'),

                        TextEntry::make('ckpn_amount')
                            ->label('CKPN Amount (Rp)')
                            ->formatStateUsing(fn (string $state) => 'Rp ' . number_format((float) $state, 2, ',', '.'))
                            ->columnSpan(3),
                    ]),

                Section::make('Audit')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('calculationRunLog.id')
                            ->label('Run Log ID'),

                        TextEntry::make('calculationRunLog.status')
                            ->label('Job Status')
                            ->formatStateUsing(fn (RunStatus $state) => $state->name),

                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('Y-m-d H:i:s'),

                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpan(2),
                    ]),
            ]);
    }

    protected static function availablePeriods(): array
    {
        return CkpnIndividualResult::distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period')
            ->mapWithKeys(fn ($period) => [$period => $period])
            ->toArray();
    }
}
