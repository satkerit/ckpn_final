<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\RunStatus;
use App\Models\CalculationRunLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ApproveCalculationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approve_calculation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Approve Calculation')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Calculation Run')
            ->modalDescription('Approving will lock all snapshots. This action cannot be undone.')
            ->modalSubmitActionLabel('Approve')
            ->action(function (CalculationRunLog $record) {
                $this->approve($record);
            });
    }

    private function approve(CalculationRunLog $record): void
    {
        if ($record->status !== RunStatus::Completed) {
            Notification::make()
                ->danger()
                ->title('Cannot Approve')
                ->body('Only Completed calculations can be approved.')
                ->send();

            return;
        }

        if ($record->status === RunStatus::Approved) {
            Notification::make()
                ->warning()
                ->title('Already Approved')
                ->body('This calculation was already approved.')
                ->send();

            return;
        }

        $record->update([
            'status' => RunStatus::Approved,
            'approved_by_user_id' => Auth::id(),
            'updated_at' => now(),
        ]);

        Notification::make()
            ->success()
            ->title('Calculation Approved')
            ->body("Run log #{$record->id} for period {$record->period} has been approved and locked.")
            ->send();
    }
}
