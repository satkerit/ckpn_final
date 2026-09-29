<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Enums\UsageType;
use App\Models\DataQualityAnomaly;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Ref: PRD Bab 7.3, FR-4.3; Phase 3 Frontend — Severity-based filtering */
#[Layout('layouts.app', ['title' => 'Anomali Data Quality'])]
class DataQualityAnomalyIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $filterPeriod = '';

    public string $filterAnomalyType = '';

    public string $filterSeverity = '';  // '' | 'critical' | 'warning' | 'info'

    public string $filterStatus = '';  // '' | 'pending' | 'resolved'

    /** Reset halaman saat filter berubah */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPeriod(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAnomalyType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSeverity(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = DataQualityAnomaly::query()
            ->with(['bucket', 'reviewedBy'])
            ->orderByRaw('CASE WHEN severity = "critical" THEN 0 WHEN severity = "warning" THEN 1 ELSE 2 END ASC')
            ->orderByRaw('CASE WHEN status = "pending" THEN 0 WHEN status = "resolved" THEN 1 ELSE 2 END ASC')
            ->orderByDesc('created_at');

        if ($this->filterPeriod !== '') {
            $query->where('period', $this->filterPeriod);
        }
        if ($this->filterAnomalyType !== '') {
            $query->where('anomaly_type', $this->filterAnomalyType);
        }
        if ($this->filterSeverity !== '') {
            $query->where('severity', $this->filterSeverity);
        }
        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        $query->when($this->search, fn ($q) => $q->where(
            fn ($w) => $w
                ->where('period', 'like', "%{$this->search}%")
                ->orWhere('anomaly_type', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")
        ));

        $records = $query->paginate(20);

        $anomalyTypes = AnomalyType::cases();
        $usageTypes = UsageType::cases();
        $severities = AnomalySeverity::cases();

        $periods = DataQualityAnomaly::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('livewire.reporting.data-quality-anomaly-index', compact(
            'records',
            'anomalyTypes',
            'usageTypes',
            'severities',
            'periods'
        ));
    }
}
