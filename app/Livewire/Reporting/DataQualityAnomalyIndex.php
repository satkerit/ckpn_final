<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Enums\AnomalyType;
use App\Enums\UsageType;
use App\Models\DataQualityAnomaly;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Ref: PRD Bab 7.3, FR-4.3 */
#[Layout('layouts.app', ['title' => 'Anomali Data Quality'])]
class DataQualityAnomalyIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $filterPeriod = '';

    public string $filterAnomalyType = '';

    public string $filterResolved = '';  // '' | '0' | '1'

    /** ID anomali yang menunggu konfirmasi resolve */
    public ?int $confirmingResolveId = null;

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

    public function updatingFilterResolved(): void
    {
        $this->resetPage();
    }

    /** Tampilkan dialog konfirmasi sebelum resolve anomali. */
    public function confirmMarkResolved(int $id): void
    {
        $this->confirmingResolveId = $id;
    }

    /** Tandai satu anomali sebagai resolved. Ref: PRD FR-4.3 */
    public function markResolved(int $id): void
    {
        $this->confirmingResolveId = null;
        $anomaly = DataQualityAnomaly::findOrFail($id);

        if ($anomaly->is_resolved) {
            return;
        }

        $anomaly->update([
            'is_resolved' => true,
            'resolved_by_user_id' => auth()->id(),
            'resolved_at' => now(),
        ]);

        $this->dispatch('notify', type: 'success', message: 'Anomali berhasil ditandai sebagai resolved.');
    }

    public function render(): View
    {
        $query = DataQualityAnomaly::query()
            ->with(['bucket', 'resolvedBy'])
            ->orderByRaw('is_resolved ASC')
            ->orderByDesc('created_at');

        if ($this->filterPeriod !== '') {
            $query->where('period', $this->filterPeriod);
        }
        if ($this->filterAnomalyType !== '') {
            $query->where('anomaly_type', $this->filterAnomalyType);
        }
        if ($this->filterResolved !== '') {
            $query->where('is_resolved', (bool) $this->filterResolved);
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

        $periods = DataQualityAnomaly::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('livewire.reporting.data-quality-anomaly-index', compact(
            'records',
            'anomalyTypes',
            'usageTypes',
            'periods'
        ));
    }
}
