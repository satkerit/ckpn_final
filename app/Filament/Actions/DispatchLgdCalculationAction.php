<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\UsageType;
use App\Enums\RunType;
use App\Jobs\LgdErCalculationJob;
use App\Jobs\LgdCsCalculationJob;
use App\Models\CalculationRunLog;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class DispatchLgdCalculationAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'dispatch_lgd_calculation')
            ->label('Jalankan Perhitungan LGD')
            ->icon('heroicon-o-play')
            ->form([
                Radio::make('lgd_method')
                    ->label('Metode LGD')
                    ->options([
                        'lgd_er' => 'LGD Expected Recoveries (ER)',
                        'lgd_cs' => 'LGD Collateral Shortfall (CS)',
                        'both' => 'Keduanya (ER + CS paralel)',
                    ])
                    ->default('both')
                    ->required(),

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
            $lgdMethod = $data['lgd_method'];
            $usageType = UsageType::from((int) $data['usage_type']);
            $calculationPeriod = $data['calculation_period'];
            $segmentDimensions = $data['segment_dimensions'] ?? [];

            $dispatchCount = 0;

            if (in_array($lgdMethod, ['lgd_er', 'both'])) {
                $runLogEr = CalculationRunLog::create([
                    'period' => $calculationPeriod,
                    'run_type' => RunType::LgdExpectedRecoveries,
                    'usage_type' => $usageType->value,
                    'status' => 'Pending',
                    'notes' => sprintf(
                        'LGD Expected Recoveries %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $calculationPeriod,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                LgdErCalculationJob::dispatch(
                    runLogId: $runLogEr->id,
                    usageType: $usageType->value,
                    calculationPeriod: $calculationPeriod,
                    segmentDimensions: $segmentDimensions,
                );
                $dispatchCount++;
            }

            if (in_array($lgdMethod, ['lgd_cs', 'both'])) {
                $runLogCs = CalculationRunLog::create([
                    'period' => $calculationPeriod,
                    'run_type' => RunType::LgdCollateralShortfall,
                    'usage_type' => $usageType->value,
                    'status' => 'Pending',
                    'notes' => sprintf(
                        'LGD Collateral Shortfall %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $calculationPeriod,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                LgdCsCalculationJob::dispatch(
                    runLogId: $runLogCs->id,
                    usageType: $usageType->value,
                    calculationPeriod: $calculationPeriod,
                    segmentDimensions: $segmentDimensions,
                );
                $dispatchCount++;
            }

            Notification::make()
                ->title('Perhitungan Dimulai')
                ->body(sprintf('%d job LGD untuk periode %s telah dikirim ke queue.', $dispatchCount, $calculationPeriod))
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
