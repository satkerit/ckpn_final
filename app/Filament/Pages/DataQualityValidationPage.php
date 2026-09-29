<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Ckpn\Services\DataQualityValidationService;
use App\Enums\UsageType;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class DataQualityValidationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Data Quality';

    protected static string $view = 'filament.pages.data-quality-validation-page';

    protected static ?int $navigationSort = 11;

    public ?string $selectedPeriod = null;

    public ?int $selectedUsageType = null;

    public ?array $validationResults = null;

    public function mount(): void
    {
        $this->selectedPeriod = now()->format('Ym');
        $this->selectedUsageType = UsageType::Konsumsi->value;
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedPeriod')
                ->label('Periode')
                ->options(fn () => self::availablePeriods())
                ->required()
                ->live(),

            Select::make('selectedUsageType')
                ->label('Jenis Penggunaan')
                ->options(collect(UsageType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->required()
                ->live(),
        ];
    }

    public function updated(): void
    {
        if ($this->selectedPeriod && $this->selectedUsageType) {
            $service = app(DataQualityValidationService::class);
            $usageType = UsageType::from($this->selectedUsageType);
            $this->validationResults = $service->getPeriodValidationSummary($this->selectedPeriod, $usageType);
        }
    }

    public function getTitle(): string
    {
        return 'Data Quality Validation Dashboard';
    }

    private static function availablePeriods(): array
    {
        $periods = [];
        $today = now();
        for ($i = 0; $i < 24; $i++) {
            $period = $today->copy()->subMonths($i)->format('Ym');
            $periods[$period] = $period;
        }
        return $periods;
    }
}
