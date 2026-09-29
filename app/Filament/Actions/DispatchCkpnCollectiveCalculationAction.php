<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Domain\Ckpn\Services\SyncCalculationService;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Filament Action: Jalankan CKPN Collective secara sinkron.
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
                    'status' => RunStatus::Pending,
                    'notes' => 'Dijalankan via UI (sync)',
                ]);

                (new SyncCalculationService)->runCkpnCollective(
                    $runLog,
                    UsageType::from($data['usage_type']),
                    $data['calculation_period'],
                );

                Notification::make()
                    ->title('Perhitungan Selesai')
                    ->body("CKPN Collective periode {$data['calculation_period']} selesai dijalankan.")
                    ->success()
                    ->send();
            });
    }
}
