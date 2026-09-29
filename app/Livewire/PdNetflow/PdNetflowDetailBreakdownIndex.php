<?php

declare(strict_types=1);

namespace App\Livewire\PdNetflow;

use App\Enums\UsageType;
use App\Jobs\PdNetflowDetailBreakdownExportJob;
use App\Models\Bucket;
use App\Models\CalculationRunLog;
use App\Models\ExportJob;
use App\Models\PdNetflowDetailBreakdown;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Detail breakdown PD Netflow per lokasi + akad + bucket + periode.
 * Pivot display dari tabel pd_netflow_detail_breakdown.
 *
 * Ref: PRD Bab 7, Phase 3+ user request — "Outstanding, Transition, Compound per lokasi + akad"
 */
#[Layout('layouts.app', ['title' => 'Detail Breakdown PD Netflow'])]
class PdNetflowDetailBreakdownIndex extends Component
{
    use WithPagination;

    #[Url(as: 'run_log')]
    public ?int $filterRunLogId = null;

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    #[Url(as: 'office')]
    public string $filterOfficeCode = '';

    #[Url(as: 'akad')]
    public string $filterAkadCode = '';

    #[Url(as: 'bucket')]
    public string $filterBucketId = '';

    #[Url(as: 'period')]
    public string $filterPeriod = '';

    // Export state
    public ?int $exportJobId = null;

    public string $exportStatus = '';

    public string $exportError = '';

    public function updatingFilterRunLogId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUsageType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterOfficeCode(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAkadCode(): void
    {
        $this->resetPage();
    }

    public function updatingFilterBucketId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPeriod(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = PdNetflowDetailBreakdown::query()
            ->with(['calculationRunLog', 'bucket'])
            ->orderByDesc('created_at');

        if ($this->filterRunLogId) {
            $query->where('calculation_run_log_id', $this->filterRunLogId);
        }
        if ($this->filterUsageType !== '') {
            $query->where('usage_type', (int) $this->filterUsageType);
        }
        if ($this->filterOfficeCode !== '') {
            $query->where('office_code', $this->filterOfficeCode);
        }
        if ($this->filterAkadCode !== '') {
            $query->where('akad_code', $this->filterAkadCode);
        }
        if ($this->filterBucketId !== '') {
            $query->where('from_bucket_id', (int) $this->filterBucketId);
        }
        if ($this->filterPeriod !== '') {
            $query->where('period', $this->filterPeriod);
        }

        $records = $query->paginate(50);

        $runLogs = CalculationRunLog::distinct()
            ->pluck('id')
            ->map(fn ($id) => CalculationRunLog::find($id))
            ->filter();

        $usageTypes = UsageType::cases();

        $officeCodes = PdNetflowDetailBreakdown::query()
            ->whereNotNull('office_code')
            ->distinct('office_code')
            ->pluck('office_code');

        $akadCodes = PdNetflowDetailBreakdown::query()
            ->whereNotNull('akad_code')
            ->distinct('akad_code')
            ->pluck('akad_code');

        $buckets = Bucket::orderBy('bucket_order')->get();

        $periods = PdNetflowDetailBreakdown::query()
            ->distinct('period')
            ->orderByDesc('period')
            ->pluck('period');

        return view('livewire.pd-netflow.pd-netflow-detail-breakdown-index', compact(
            'records',
            'runLogs',
            'usageTypes',
            'officeCodes',
            'akadCodes',
            'buckets',
            'periods'
        ));
    }

    /**
     * Dispatch export ke queue — tidak blocking browser.
     * File disimpan di storage/app/exports/, user polling via pollExportStatus().
     * Ref: PRD Bab 7
     */
    public function exportExcel(): void
    {
        $filters = array_filter([
            'run_log_id' => $this->filterRunLogId,
            'usage_type' => $this->filterUsageType !== '' ? (int) $this->filterUsageType : null,
            'office_code' => $this->filterOfficeCode ?: null,
            'akad_code' => $this->filterAkadCode ?: null,
            'bucket_id' => $this->filterBucketId !== '' ? (int) $this->filterBucketId : null,
            'period' => $this->filterPeriod ?: null,
        ]);

        $exportJob = ExportJob::create([
            'type' => 'pd_netflow_detail_breakdown',
            'status' => 'pending',
            'params' => $filters,
        ]);

        PdNetflowDetailBreakdownExportJob::dispatch($exportJob->id, $filters)
            ->onQueue('ckpn-calculation');

        $this->exportJobId = $exportJob->id;
        $this->exportStatus = 'processing';
        $this->exportError = '';
    }

    /** Dipanggil oleh Livewire polling setiap 3 detik saat export sedang berjalan. */
    public function pollExportStatus(): void
    {
        if ($this->exportJobId === null || $this->exportStatus !== 'processing') {
            return;
        }

        $job = ExportJob::find($this->exportJobId);
        if (! $job) {
            return;
        }

        $this->exportStatus = $job->status;
        if ($job->isFailed()) {
            $this->exportError = $job->error_message ?? 'Export gagal.';
        }
    }
}
