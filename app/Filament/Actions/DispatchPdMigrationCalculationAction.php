<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\UsageType;
use App\Enums\RunType;
use App\Jobs\PdMigrationCalculationJob;
use App\Models\CalculationRunLog;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class DispatchPdMigrationCalculationAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'dispatch_pd_migration')
            ->label('Jalankan Perhitungan PD Migration')
            ->icon('heroicon-o-play')
            ->form([
                Select::make('usage_type')
                    ->label('Jenis Penggunaan')
                    ->options(collect(UsageType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required(),

                Select::make('calculation_period')
                    ->label('Periode Kalkulasi (YYYYMM)')
                    ->options(fn () => self::availablePeriods())
                    ->required(),

                CheckboxList::make('segment_dimensions')
                    ->label('Dimensi Segmentasi')
                    ->options([
                        'office_code' => 'Kode Kantor',
                        'akad_code' => 'Kode Akad',
                        'usage_type' => 'Jenis Penggunaan',
                    ])
                    ->helperText('Kosong = tanpa segmentasi (global aggregate). Pilih satu atau lebih untuk segmentasi dinamis.')
                    ->default([]),
            ])
            ->action(function (array $data) {
                self::dispatchJob($data);
            });
    }

    private static function availablePeriods(): array
    {
        $periods = [];
        $today = now();
        for ($i = 0; $i < 12; $i++) {
            $period = $today->copy()->subMonths($i)->format('Ym');
            $periods[$period] = $period;
        }
        return $periods;
    }

    private static function dispatchJob(array $data): void
    {
        try {
            $usageType = UsageType::from((int) $data['usage_type']);
            $calculationPeriod = $data['calculation_period'];
            $segmentDimensions = $data['segment_dimensions'] ?? [];

            $runLog = CalculationRunLog::create([
                'period' => $calculationPeriod,
                'run_type' => RunType::PdMigration,
                'usage_type' => $usageType->value,
                'status' => 'Pending',
                'notes' => sprintf(
                    'PD Migration %s periode %s (segmentasi: %s)',
                    $usageType->label(),
                    $calculationPeriod,
                    empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                ),
            ]);

            PdMigrationCalculationJob::dispatch(
                runLogId: $runLog->id,
                usageType: $usageType->value,
                calculationPeriod: $calculationPeriod,
                segmentDimensions: $segmentDimensions,
            );

            Notification::make()
                ->title('Perhitungan Dimulai')
                ->body(sprintf('Job PD Migration untuk periode %s telah dikirim ke queue.', $calculationPeriod))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menjalankan Perhitungan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
