<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\CkpnCollectiveCalculationJob;
use App\Models\CalculationRunLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Filament Action: Dispatch CKPN Collective Calculation Job.
 *
 * Ref: PRD Bab 11, 14
 */
final class DispatchCkpnCollectiveCalculationAction
{
    public static function make(): Action
    {
        return Action::make('dispatchCkpnCollectiveCalculation')
            ->label('Jalankan Perhitungan CKPN Collective')
            ->form([
                TextInput::make('calculation_period')
                    ->label('Period (YYYYMM)')
                    ->required()
                    ->regex('/^\d{6}$/')
                    ->placeholder('202309'),
                Select::make('usage_type')
                    ->label('Usage Type')
                    ->options(UsageType::class)
                    ->required(),
            ])
            ->action(function (array $data) {
                $runLog = CalculationRunLog::create([
                    'period' => $data['calculation_period'],
                    'run_type' => RunType::CkpnCollective,
                    'status' => \App\Enums\RunStatus::Pending,
                    'notes' => 'Dispatched via UI',
                ]);

                CkpnCollectiveCalculationJob::dispatch(
                    $runLog->id,
                    $data['calculation_period'],
                    UsageType::from($data['usage_type']),
                );

                Notification::make()
                    ->title('Job Dispatched')
                    ->body("CKPN Collective calculation for {$data['calculation_period']} queued. Monitor at Job Monitor.")
                    ->success()
                    ->send();
            });
    }
}
