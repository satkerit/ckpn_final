<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecords;
use Illuminate\Database\Eloquent\Builder;

class CalculationRunLogResource extends Resource
{
    protected static ?string $model = CalculationRunLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Calculation Run Logs';

    protected static ?string $pluralModelLabel = 'Calculation Run Logs';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('period')
                    ->label('Periode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('run_type')
                    ->label('Tipe Kalkulasi')
                    ->formatStateUsing(fn (RunType $state) => $state->name)
                    ->badge()
                    ->color(fn (RunType $state) => match ($state) {
                        RunType::PdNetflow => 'blue',
                        RunType::PdMigration => 'cyan',
                        RunType::LgdExpectedRecoveries => 'green',
                        RunType::LgdCollateralShortfall => 'lime',
                        RunType::CkpnIndividual => 'purple',
                        RunType::CkpnCollective => 'violet',
                    }),

                TextColumn::make('usage_type')
                    ->label('Jenis Penggunaan')
                    ->formatStateUsing(fn (UsageType $state) => $state->label())
                    ->badge(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->color(fn (RunStatus $state) => match ($state) {
                        RunStatus::Pending => 'warning',
                        RunStatus::Processing => 'info',
                        RunStatus::Completed => 'success',
                        RunStatus::CompletedWithWarning => 'warning',
                        RunStatus::Failed => 'danger',
                        RunStatus::Approved => 'success',
                    })
                    ->formatStateUsing(fn (RunStatus $state) => $state->name),

                TextColumn::make('triggered_by.name')
                    ->label('Dipicu oleh')
                    ->sortable(),

                TextColumn::make('approvedBy.name')
                    ->label('Disetujui oleh')
                    ->sortable()
                    ->formatStateUsing(fn (?string $state) => $state ?? '—'),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('run_type')
                    ->label('Tipe Kalkulasi')
                    ->options(collect(RunType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->name])),

                SelectFilter::make('usage_type')
                    ->label('Jenis Penggunaan')
                    ->options(collect(UsageType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(RunStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->name])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Kalkulasi')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('period')
                            ->label('Periode'),

                        TextEntry::make('run_type')
                            ->label('Tipe Kalkulasi')
                            ->formatStateUsing(fn (RunType $state) => $state->name),

                        TextEntry::make('usage_type')
                            ->label('Jenis Penggunaan')
                            ->formatStateUsing(fn (UsageType $state) => $state->label()),

                        TextEntry::make('office_code')
                            ->label('Kode Kantor')
                            ->formatStateUsing(fn (?string $state) => $state ?? '(Global)'),

                        TextEntry::make('akad_code')
                            ->label('Kode Akad')
                            ->formatStateUsing(fn (?string $state) => $state ?? '(Global)'),
                    ]),

                Section::make('Status & Audit')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn (RunStatus $state) => $state->name)
                            ->badge()
                            ->color(fn (RunStatus $state) => match ($state) {
                                RunStatus::Completed => 'success',
                                RunStatus::Processing => 'info',
                                RunStatus::Pending => 'warning',
                                RunStatus::Failed => 'danger',
                                RunStatus::Approved => 'success',
                                RunStatus::CompletedWithWarning => 'warning',
                            }),

                        TextEntry::make('triggeredBy.name')
                            ->label('Dipicu oleh'),

                        TextEntry::make('started_at')
                            ->label('Mulai')
                            ->dateTime('Y-m-d H:i:s'),

                        TextEntry::make('completed_at')
                            ->label('Selesai')
                            ->dateTime('Y-m-d H:i:s'),

                        TextEntry::make('approvedBy.name')
                            ->label('Disetujui oleh')
                            ->formatStateUsing(fn (?string $state) => $state ?? '(Belum disetujui)'),

                        TextEntry::make('updated_at')
                            ->label('Terakhir Diperbarui')
                            ->dateTime('Y-m-d H:i:s'),
                    ]),

                Section::make('Error & Notes')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Pesan Error')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?string $state) => $state ?? '(Tidak ada error)'),

                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->markdown(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalculationRunLogs::route('/'),
            'view' => Pages\ViewCalculationRunLogs::route('/{record}'),
        ];
    }
}
