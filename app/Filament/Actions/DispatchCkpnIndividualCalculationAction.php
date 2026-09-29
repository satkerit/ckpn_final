<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\UsageType;
use App\Enums\RunType;
use App\Jobs\CkpnIndividualCalculationJob;
use App\Models\CalculationRunLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class DispatchCkpnIndividualCalculationAction extends Action
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'dispatch_ckpn_individual_calculation')
            ->label('Jalankan CKPN Individual')
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

            $runLog = CalculationRunLog::create([
                'period' => $calculationPeriod,
                'run_type' => RunType::CkpnIndividual,
                'usage_type' => $usageType->value,
                'status' => 'Pending',
                'notes' => sprintf(
                    'CKPN Individual %s periode %s',
                    $usageType->label(),
                    $calculationPeriod,
                ),
            ]);

            CkpnIndividualCalculationJob::dispatch(
                runLogId: $runLog->id,
                usageType: $usageType->value,
                calculationPeriod: $calculationPeriod,
            );

            Notification::make()
                ->title('Perhitungan Dimulai')
                ->body(sprintf('Job CKPN Individual %s periode %s telah dikirim ke queue.', $usageType->label(), $calculationPeriod))
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
