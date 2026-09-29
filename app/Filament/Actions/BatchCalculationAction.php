<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Domain\Ckpn\Services\SyncCalculationService;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
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
                    ->helperText('Pilih metode untuk dijalankan secara berurutan (sinkron).')
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
                self::runBatch($data);
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

    private static function runBatch(array $data): void
    {
        try {
            $period = $data['calculation_period'];
            $usageType = UsageType::from((int) $data['usage_type']);
            $methods = $data['methods'] ?? [];
            $segmentDimensions = $data['segment_dimensions'] ?? [];

            $runCount = 0;
            $runner = new SyncCalculationService;

            // PD Netflow
            if (in_array('pd_netflow', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::PdNetflow,
                    'usage_type' => $usageType->value,
                    'status' => RunStatus::Pending,
                    'notes' => sprintf(
                        'Batch: PD Netflow %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                $runner->runPdNetflow($runLog, $usageType, $period, segmentDimensions: $segmentDimensions);
                $runCount++;
            }

            // PD Migration
            if (in_array('pd_migration', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::PdMigration,
                    'usage_type' => $usageType->value,
                    'status' => RunStatus::Pending,
                    'notes' => sprintf(
                        'Batch: PD Migration %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                $runner->runPdMigration($runLog, $usageType, $period, segmentDimensions: $segmentDimensions);
                $runCount++;
            }

            // LGD Expected Recoveries
            if (in_array('lgd_er', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::LgdEr,
                    'usage_type' => $usageType->value,
                    'status' => RunStatus::Pending,
                    'notes' => sprintf(
                        'Batch: LGD Expected Recoveries %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                $runner->runLgdEr($runLog, $usageType, $period, segmentDimensions: $segmentDimensions);
                $runCount++;
            }

            // LGD Collateral Shortfall
            if (in_array('lgd_cs', $methods)) {
                $runLog = CalculationRunLog::create([
                    'period' => $period,
                    'run_type' => RunType::LgdCs,
                    'usage_type' => $usageType->value,
                    'status' => RunStatus::Pending,
                    'notes' => sprintf(
                        'Batch: LGD Collateral Shortfall %s periode %s (segmentasi: %s)',
                        $usageType->label(),
                        $period,
                        empty($segmentDimensions) ? 'tidak ada' : implode(', ', $segmentDimensions),
                    ),
                ]);

                $runner->runLgdCs($runLog, $usageType, $period, segmentDimensions: $segmentDimensions);
                $runCount++;
            }

            Notification::make()
                ->title('Batch Perhitungan Selesai')
                ->body(sprintf(
                    '%d perhitungan untuk %s %s selesai dijalankan.',
                    $runCount,
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
