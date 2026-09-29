<?php

declare(strict_types=1);

namespace App\Livewire\PdNetflow;

use App\Enums\UsageType;
use App\Jobs\PdNetflowPivotExportJob;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\ExportJob;
use App\Models\FinancingAccount;
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

    #[Url(as: 'office_code')]
    public string $filterOfficeCode = '';

    #[Url(as: 'akad_code')]
    public string $filterAkadCode = '';

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

    /** Cek apakah sudah ada snapshot tersimpan untuk kombinasi periode + usage_type + office_code + akad_code. */
    private function checkSnapshot(): void
    {
        if ($this->filterPeriode === '') {
            $this->hasSnapshot = false;

            return;
        }

        $usageTypeValue = $this->filterUsageType !== '' ? (int) $this->filterUsageType : null;
        $officeCode = $this->filterOfficeCode !== '' ? $this->filterOfficeCode : null;
        $akadCode = $this->filterAkadCode !== '' ? $this->filterAkadCode : null;

        $this->hasSnapshot = PdNetflowCalculationHistory::query()
            ->where('calculation_period', $this->filterPeriode)
            ->when(
                $usageTypeValue !== null,
                fn ($q) => $q->where('usage_type', $usageTypeValue),
                fn ($q) => $q->whereNull('usage_type'),
            )
            ->when(
                $officeCode !== null,
                fn ($q) => $q->where('office_code', $officeCode),
                fn ($q) => $q->whereNull('office_code'),
            )
            ->when(
                $akadCode !== null,
                fn ($q) => $q->where('akad_code', $akadCode),
                fn ($q) => $q->whereNull('akad_code'),
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

    /** Load pivot dari snapshot DB tanpa hitung ulang.
     * Dipanggil saat hasSnapshot = true dan user klik "Tampilkan".
     */
    public function loadFromSnapshot(): void
    {
        $this->errorMessage = '';
        $usageTypeValue = $this->filterUsageType !== '' ? (int) $this->filterUsageType : null;
        $officeCode = $this->filterOfficeCode !== '' ? $this->filterOfficeCode : null;
        $akadCode = $this->filterAkadCode !== '' ? $this->filterAkadCode : null;

        $history = PdNetflowCalculationHistory::query()
            ->where('calculation_period', $this->filterPeriode)
            ->when(
                $usageTypeValue !== null,
                fn ($q) => $q->where('usage_type', $usageTypeValue),
                fn ($q) => $q->whereNull('usage_type'),
            )
            ->when(
                $officeCode !== null,
                fn ($q) => $q->where('office_code', $officeCode),
                fn ($q) => $q->whereNull('office_code'),
            )
            ->when(
                $akadCode !== null,
                fn ($q) => $q->where('akad_code', $akadCode),
                fn ($q) => $q->whereNull('akad_code'),
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

        $usageType = $this->filterUsageType === 'all' ? null : $this->filterUsageType;
        $officeCode = $this->filterOfficeCode !== '' ? $this->filterOfficeCode : null;
        $akadCode = $this->filterAkadCode !== '' ? $this->filterAkadCode : null;

        $service = new PdNetflowDetailService;
        $result = $service->calculate(
            $this->filterPeriode,
            $usageType,
            $officeCode,
            $akadCode,
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
        $usageTypeValue = $usageType !== null ? (int) $usageType : null;
        PdNetflowCalculationHistory::updateOrCreate(
            [
                'calculation_period' => $this->filterPeriode,
                'usage_type' => $usageTypeValue,
                'office_code' => $officeCode,
                'akad_code' => $akadCode,
                // simpan ke run_log dummy (id=0) untuk pivot on-the-fly (bukan job batch)
                'calculation_run_log_id' => CalculationRunLog::query()
                    ->where('period', $this->filterPeriode)
                    ->where('usage_type', $usageTypeValue)
                    ->when($officeCode !== null, fn ($q) => $q->where('office_code', $officeCode))
                    ->when($akadCode !== null, fn ($q) => $q->where('akad_code', $akadCode))
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

    /** Hapus snapshot dari DB untuk periode + usage_type + office_code + akad_code yang dipilih. */
    public function deleteSnapshot(): void
    {
        $this->authorize('deleteAny', PdNetflowResult::class);

        $this->confirmingAction = '';
        $usageTypeValue = $this->filterUsageType !== '' && $this->filterUsageType !== 'all'
            ? (int) $this->filterUsageType
            : null;
        $officeCode = $this->filterOfficeCode !== '' ? $this->filterOfficeCode : null;
        $akadCode = $this->filterAkadCode !== '' ? $this->filterAkadCode : null;

        PdNetflowCalculationHistory::query()
            ->where('calculation_period', $this->filterPeriode)
            ->when(
                $usageTypeValue !== null,
                fn ($q) => $q->where('usage_type', $usageTypeValue),
                fn ($q) => $q->whereNull('usage_type'),
            )
            ->when(
                $officeCode !== null,
                fn ($q) => $q->where('office_code', $officeCode),
                fn ($q) => $q->whereNull('office_code'),
            )
            ->when(
                $akadCode !== null,
                fn ($q) => $q->where('akad_code', $akadCode),
                fn ($q) => $q->whereNull('akad_code'),
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
        $officeCode = $this->filterOfficeCode !== '' ? $this->filterOfficeCode : null;
        $akadCode = $this->filterAkadCode !== '' ? $this->filterAkadCode : null;

        $exportJob = ExportJob::create([
            'type' => 'pd_netflow_pivot',
            'status' => 'pending',
            'params' => [
                'period' => $this->filterPeriode,
                'usage_type' => $usageType,
                'office_code' => $officeCode,
                'akad_code' => $akadCode,
            ],
        ]);

        PdNetflowPivotExportJob::dispatch(
            $exportJob->id,
            $this->filterPeriode,
            $usageType,
            $officeCode,
            $akadCode,
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
        $periods = CkpnPeriod::query()
            ->orderByDesc('period')
            ->pluck('period');

        // office_code & akad_code berada di financing_accounts (bukan financing_account_periods);
        // sumber sama dengan ClassifyPeriodDataJob — Ref: PRD Bab 5
        $offices = FinancingAccount::query()
            ->whereHas('accountPeriods', fn ($q) => $q->where('period', $this->filterPeriode))
            ->distinct()
            ->orderBy('office_code')
            ->pluck('office_code')
            ->filter()
            ->values();

        $akadCodes = FinancingAccount::query()
            ->whereHas('accountPeriods', fn ($q) => $q->where('period', $this->filterPeriode))
            ->distinct()
            ->orderBy('akad_code')
            ->pluck('akad_code')
            ->filter()
            ->values();

        return view('livewire.pd-netflow.pd-netflow-pivot-index', [
            'periods' => $periods,
            'usageTypes' => UsageType::cases(),
            'offices' => $offices,
            'akadCodes' => $akadCodes,
        ]);
    }
}
