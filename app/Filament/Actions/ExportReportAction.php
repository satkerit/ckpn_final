<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\UsageType;
use App\Jobs\ReportExportJob;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ExportReportAction extends Action implements HasForms
{
    use InteractsWithForms;

    public static function getDefaultName(): ?string
    {
        return 'export_report';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Export Final Report (PDF)')
            ->icon('heroicon-o-document-text')
            ->color('info')
            ->form([
                Select::make('usage_type')
                    ->label('Usage Type')
                    ->options(collect(UsageType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->required(),

                TextInput::make('period')
                    ->label('Period (YYYYMM)')
                    ->placeholder('202612')
                    ->required()
                    ->regex('/^\d{6}$/'),

                TextInput::make('recipient_email')
                    ->label('Send to Email (optional)')
                    ->email()
                    ->placeholder('user@example.com'),
            ])
            ->action(function (array $data) {
                $this->dispatch($data);
            });
    }

    private function dispatch(array $data): void
    {
        ReportExportJob::dispatch(
            $data['period'],
            UsageType::from($data['usage_type']),
            $data['recipient_email'] ?? null,
        );

        Notification::make()
            ->success()
            ->title('Report Export Queued')
            ->body("Report for period {$data['period']} has been queued for generation.")
            ->send();
    }
}
