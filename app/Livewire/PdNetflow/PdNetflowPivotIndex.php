<?php

declare(strict_types=1);

namespace App\Livewire\PdNetflow;

use App\Enums\UsageType;
use App\Jobs\PdNetflowPivotExportJob;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\ExportJob;
use App\Models\PdNetflowCalculationHistory;
use App\Models\PdNetflowResult;
use App\Services\PdNetflowDetailService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pivot table PD Netflow on-the-fly dari financing_account_periods.
 * Ref: PRD Bab 7
 */
#[Layout('layouts.app', ['title' => 'Pivot PD Netflow'])]
class PdNetflowPivotIndex extends Component
{
    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    // Data section outstanding
    public array $outstandingPeriods = [];

    public array $outstanding = [];

    // Data section transition
    public array $transitionPeriods = [];

    public array $transition = [];

    // Data section compound flow
    public array $compoundPeriods = [];

    public array $compound = [];

    public array $compoundAvg = [];

    // Data section projected rate
    public array $projectionPeriods = [];

    public array $projection = [];

    // Buckets & state
    public array $buckets = [];

    public bool $dataLoaded = false;

    public string $errorMessage = '';

    /** true jika sudah ada snapshot tersimpan untuk periode+usage_type yang dipilih */
    public bool $hasSnapshot = false;

    /** Aksi yang menunggu konfirmasi: 'delete_snapshot' | '' */
    public string $confirmingAction = '';

    // Export async state
    public ?int $exportJobId = null;

    public string $exportStatus = ''; // '' | processing | done | failed

    public string $exportError = '';

    public function updatedFilterPeriode(): void
    {
        $this->resetData();
        $this->checkSnapshot();
    }

    public function updatedFilterUsageType(): void
    {
        $this->resetData();
        $this->checkSnapshot();
    }

    /** Cek apakah sudah ada snapshot tersimpan untuk kombinasi periode + usage_type. */
    private function checkSnapshot(): void
    {
        if ($this->filterPeriode === '') {
            $this->hasSnapshot = false;

            return;
        }

        $usageTypeValue = $this->filterUsageType !== '' ? (int) $this->filterUsageType : null;

        $this->hasSnapshot = PdNetflowCalculationHistory::query()
            ->where('calculation_period', $this->filterPeriode)
            ->when(
                $usageTypeValue !== null,
                fn ($q) => $q->where('usage_type', $usageTypeValue),
                fn ($q) => $q->whereNull('usage_type'),
            )
            ->exists();
    }

    private function resetData(): void
    {
        $this->reset([
            'outstandingPeriods',
            'outstanding',
            'transitionPeriods',
            'transition',
            'compoundPeriods',
            'compound',
            'compoundAvg',
            'projectionPeriods',
            'projection',
            'buckets',
            'dataLoaded',
            'errorMessage',
            'exportJobId',
            'exportStatus',
            'exportError',
        ]);
    }

    /**
     * Load pivot dari snapshot DB tanpa hitung ulang.
     * Dipanggil saat hasSnapshot = true dan user klik "Tampilkan".
     */
    public function loadFromSnapshot(): void
    {
        $this->errorMessage = '';
        $usageTypeValue = $this->filterUsageType !== '' ? (int) $this->filterUsageType : null;

        $history = PdNetflowCalculationHistory::query()
            ->where('calculation_period', $this->filterPeriode)
            ->when(
                $usageTypeValue !== null,
                fn ($q) => $q->where('usage_type', $usageTypeValue),
                fn ($q) => $q->whereNull('usage_type'),
            )
            ->latest()
            ->first();

        if (! $history) {
            $this->errorMessage = 'Snapshot tidak ditemukan. Klik "Hitung Ulang" untuk menghitung.';

            return;
        }

        $data = $history->history_data;
        $this->outstandingPeriods = $data['outstanding_periods'] ?? [];
        $this->transitionPeriods = $data['transition_periods'] ?? [];
        $this->compoundPeriods = $data['compound_periods'] ?? [];
        $this->projectionPeriods = $data['projection_periods'] ?? [];
        $this->buckets = $data['buckets'] ?? [];
        $this->outstanding = $data['outstanding'] ?? [];
        $this->transition = $data['transition'] ?? [];
        $this->compound = $data['compound'] ?? [];
        $this->compoundAvg = $data['compound_avg'] ?? [];
        $this->projection = $data['projection'] ?? [];
        $this->dataLoaded = true;
    }

    /** Kalkulasi on-the-fly + simpan snapshot ke DB. */
    public function loadData(): void
    {
        $this->errorMessage = '';

        if ($this->filterPeriode === '' || ! preg_match('/^\d{6}$/', $this->filterPeriode)) {
            $this->errorMessage = 'Pilih periode terlebih dahulu.';

            return;
        }

        if ($this->filterUsageType === '') {
            $this->errorMessage = 'Pilih jenis penggunaan atau Semua Jenis Penggunaan.';

            return;
        }

        $service = new PdNetflowDetailService;
        $result = $service->calculate(
            $this->filterPeriode,
            $this->filterUsageType === 'all' ? null : $this->filterUsageType,
            null,
            null,
        );

        $this->outstandingPeriods = $result['outstanding_periods'];
        $this->outstanding = $result['outstanding'];
        $this->transitionPeriods = $result['transition_periods'];
        $this->transition = $result['transition'];
        $this->compoundPeriods = $result['compound_periods'];
        $this->compound = $result['compound'];
        $this->compoundAvg = $result['compound_avg'];
        $this->projectionPeriods = $result['projection_periods'];
        $this->projection = $result['projection'];
        $this->buckets = $result['buckets'];
        $this->dataLoaded = true;

        // Simpan / timpa snapshot agar "Tampilkan" tidak perlu hitung ulang. Ref: PRD Bab 7
        $usageTypeValue = $this->filterUsageType !== 'all' ? (int) $this->filterUsageType : null;
        PdNetflowCalculationHistory::updateOrCreate(
            [
                'calculation_period' => $this->filterPeriode,
                'usage_type' => $usageTypeValue,
                // simpan ke run_log dummy (id=0) untuk pivot on-the-fly (bukan job batch)
                'calculation_run_log_id' => CalculationRunLog::query()
                    ->where('period', $this->filterPeriode)
                    ->where('usage_type', $usageTypeValue)
                    ->latest()
                    ->value('id') ?? 0,
            ],
            ['history_data' => $result],
        );
        $this->hasSnapshot = true;
    }

    /** Tampilkan dialog konfirmasi sebelum hapus snapshot. */
    public function confirmDeleteSnapshot(): void
    {
        if ($this->filterPeriode === '') {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'delete_snapshot';
    }

    /** Hapus snapshot dari DB untuk periode + usage_type yang dipilih. */
    public function deleteSnapshot(): void
    {
        $this->authorize('deleteAny', PdNetflowResult::class);

        $this->confirmingAction = '';
        $usageTypeValue = $this->filterUsageType !== '' && $this->filterUsageType !== 'all'
            ? (int) $this->filterUsageType
            : null;

        PdNetflowCalculationHistory::query()
            ->where('calculation_period', $this->filterPeriode)
            ->when(
                $usageTypeValue !== null,
                fn ($q) => $q->where('usage_type', $usageTypeValue),
                fn ($q) => $q->whereNull('usage_type'),
            )
            ->delete();

        $this->resetData();
        $this->hasSnapshot = false;
        $this->dispatch('notify', type: 'success', message: "Snapshot periode {$this->filterPeriode} berhasil dihapus.");
    }

    /**
     * Dispatch export ke queue — tidak blocking browser.
     * File disimpan di storage/app/exports/, user polling via pollExportStatus().
     * Ref: PRD Bab 7
     */
    public function exportExcel(): void
    {
        if ($this->filterPeriode === '') {
            $this->errorMessage = 'Pilih periode terlebih dahulu.';

            return;
        }

        $usageType = $this->filterUsageType === 'all' ? null : $this->filterUsageType;

        $exportJob = ExportJob::create([
            'type' => 'pd_netflow_pivot',
            'status' => 'pending',
            'params' => [
                'period' => $this->filterPeriode,
                'usage_type' => $usageType,
            ],
        ]);

        PdNetflowPivotExportJob::dispatch(
            $exportJob->id,
            $this->filterPeriode,
            $usageType,
        )->onQueue('ckpn-calculation');

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

    public function render(): View
    {
        return view('livewire.pd-netflow.pd-netflow-pivot-index', [
            'periods' => CkpnPeriod::query()
                ->orderByDesc('period')
                ->pluck('period'),
            'usageTypes' => UsageType::cases(),
        ]);
    }
}
