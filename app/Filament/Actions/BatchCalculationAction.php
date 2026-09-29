<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\LgdCsCalculationJob;
use App\Jobs\LgdErCalculationJob;
use App\Jobs\PdMigrationCalculationJob;
use App\Jobs\PdNetflowCalculationJob;
use App\Models\CalculationRunLog;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class BatchCalculationAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'batch_calculation')
            ->label('Jalankan Batch Perhitungan')
            ->icon('heroicon-o-rocket-launch')
            ->form([
                Select::make('calculation_period')
                    ->label('Periode Kalkulasi (YYYYMM)')
                    ->options(fn () => self::availablePeriods())
                    ->required(),

                Select::make('usage_type')
                    ->label('Jenis Penggunaan')
                    ->options(collect(UsageType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required(),

                CheckboxList::make('methods')
                    ->label('Metode Kalkulasi')
                    ->options([
                        'pd_netflow' => 'PD Netflow',
                        'pd_migration' => 'PD Migration',
                        'lgd_er' => 'LGD Expected Recoveries',
                        'lgd_cs' => 'LGD Collateral Shortfall',
                    ])
                    ->default(['pd_netflow', 'pd_migration', 'lgd_er', 'lgd_cs'])
                    ->helperText('Pilih metode untuk dijalankan paralel. Semua dijalankan async via queue.')
                    ->required(),

                CheckboxList::make('segment_dimensions')
                    ->label('Dimensi Segmentasi')
                    ->options([
                        'office_code' => 'Kode Kantor',
                        'akad_code' => 'Kode Akad',
                        'usage_type' => 'Jenis Penggunaan',
                    ])
                    ->helperText('Kosong = tanpa segmentasi (global aggregate).')
                    ->default([]),
            ])
            ->action(function (array $data) {
                self::dispatchBatch($data);
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

    private static function dispatchBatch(array $data): void
    {
        try {
            $period = $data['calculation_period'];
            $usageType = UsageType::from((int) $data['usage_type']);
            $methods = $data['methods'] ?? [];
            $segmentDimensions = $data['segment_dimensions'] ?? [];

            $dispatchCount = 0;

            // PD Netflow
            if (in_array('pd_netflow', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::PdNetflow,
                    'usage_type' => $usageType->value,
                    'status' => 'Pending',
                    'notes' => sprintf(
                        'Batch: PD Netflow %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                PdNetflowCalculationJob::dispatch(
                    runLogId: $runLog->id,
                    usageType: $usageType->value,
                    calculationPeriod: $period,
                    segmentDimensions: $segmentDimensions,
                );
                $dispatchCount++;
            }

            // PD Migration
            if (in_array('pd_migration', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::PdMigration,
                    'usage_type' => $usageType->value,
                    'status' => 'Pending',
                    'notes' => sprintf(
                        'Batch: PD Migration %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                PdMigrationCalculationJob::dispatch(
                    runLogId: $runLog->id,
                    usageType: $usageType->value,
                    calculationPeriod: $period,
                    segmentDimensions: $segmentDimensions,
                );
                $dispatchCount++;
            }

            // LGD Expected Recoveries
            if (in_array('lgd_er', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::LgdEr,
                    'usage_type' => $usageType->value,
                    'status' => 'Pending',
                    'notes' => sprintf(
                        'Batch: LGD Expected Recoveries %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                LgdErCalculationJob::dispatch(
                    runLogId: $runLog->id,
                    usageType: $usageType->value,
                    calculationPeriod: $period,
                    segmentDimensions: $segmentDimensions,
                );
                $dispatchCount++;
            }

            // LGD Collateral Shortfall
            if (in_array('lgd_cs', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::LgdCs,
                    'usage_type' => $usageType->value,
                    'status' => 'Pending',
                    'notes' => sprintf(
                        'Batch: LGD Collateral Shortfall %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                LgdCsCalculationJob::dispatch(
                    runLogId: $runLog->id,
                    usageType: $usageType->value,
                    calculationPeriod: $period,
                    segmentDimensions: $segmentDimensions,
                );
                $dispatchCount++;
            }

            Notification::make()
                ->title('Batch Perhitungan Dimulai')
                ->body(sprintf(
                    '%d job untuk %s %s telah dikirim ke queue. Pantau status di Job Monitor.',
                    $dispatchCount,
                    $usageType->label(),
                    $period,
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menjalankan Batch Perhitungan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
