<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Read-only log eksekusi perhitungan. Ref: PRD Bab 13.2 & 16 */
#[Layout('layouts.app', ['title' => 'Log Kalkulasi'])]
class CalculationRunLogIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $filterRunType = '';

    public string $filterStatus = '';

    public string $filterPeriod = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRunType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPeriod(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = CalculationRunLog::query()
            ->with(['triggeredBy', 'approvedBy'])
            ->orderByDesc('started_at')
            ->orderByDesc('id');

        if ($this->filterRunType !== '') {
            $query->where('run_type', $this->filterRunType);
        }
        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterPeriod !== '') {
            $query->where('period', $this->filterPeriod);
        }

        $query->when($this->search, fn ($q) => $q->where(
            fn ($w) => $w
                ->where('period', 'like', "%{$this->search}%")
                ->orWhere('run_type', 'like', "%{$this->search}%")
                ->orWhere('status', 'like', "%{$this->search}%")
                ->orWhere('notes', 'like', "%{$this->search}%")
        ));

        $records = $query->paginate(20);

        $runTypes = RunType::cases();
        $statuses = RunStatus::cases();
        $usageTypes = UsageType::cases();

        $periods = CalculationRunLog::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('livewire.admin.calculation-run-log-index', compact(
            'records',
            'runTypes',
            'statuses',
            'usageTypes',
            'periods'
        ));
    }
}
