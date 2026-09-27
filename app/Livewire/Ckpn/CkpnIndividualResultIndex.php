<?php

declare(strict_types=1);

namespace App\Livewire\Ckpn;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\CkpnIndividualCalculationJob;
use App\Models\CalculationRunLog;
use App\Models\CkpnIndividualResult;
use App\Models\CkpnPeriod;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman CKPN Individual: pilih periode → tampilkan → hitung.
 * Alur sama dengan klasifikasi. Ref: PRD Bab 6.1
 */
#[Layout('layouts.app', ['title' => 'CKPN Individual'])]
class CkpnIndividualResultIndex extends Component
{
    use WithPagination;

    /** Periode yang dipilih di panel kontrol */
    public string $individualPeriod = '';

    /** Apakah tabel hasil ditampilkan (setelah klik "Tampil") */
    public bool $showTable = false;

    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url(as: 'search')]
    public string $search = '';

    public bool $isRunning = false;

    /** Aksi yang menunggu konfirmasi: 'hitung' | 'hapus' | 'bulk_hapus' | 'hapus_periode' | '' */
    public string $confirmingAction = '';

    /** ID baris yang dikonfirmasi untuk dihapus (single delete) */
    public ?int $deletingId = null;

    /** Bulk select */
    public array $selectedIds = [];

    public bool $selectAll = false;

    public string $flashMessage = '';

    public string $flashType = 'success';

    public function mount(): void
    {
        // Pre-select periode terbaru
        $latest = CkpnPeriod::query()->orderByDesc('period')->value('period');
        if ($latest) {
            $this->individualPeriod = (string) $latest;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIndividualPeriod(): void
    {
        $this->showTable = false;
        $this->filterPeriode = '';
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function tampilkan(): void
    {
        if ($this->individualPeriod === '') {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode terlebih dahulu.');

            return;
        }

        $this->filterPeriode = $this->individualPeriod;
        $this->showTable = true;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    /** Tampilkan dialog konfirmasi sebelum hitung. Ref: PRD Bab 6.1 */
    public function confirmHitung(): void
    {
        if ($this->individualPeriod === '') {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode terlebih dahulu.');

            return;
        }

        $period = CkpnPeriod::where('period', $this->individualPeriod)->first();

        if (! $period) {
            $this->dispatch('notify', type: 'error', message: 'Periode tidak ditemukan.');

            return;
        }

        if (! $period->is_classified) {
            $this->dispatch('notify', type: 'error', message: 'Klasifikasikan data terlebih dahulu sebelum menghitung CKPN Individual.');

            return;
        }

        $this->confirmingAction = 'hitung';
    }

    /**
     * Dispatch CkpnIndividualCalculationJob untuk semua UsageType pada periode terpilih.
     * Idempotent: skip UsageType yang sedang pending/processing. Ref: AGENTS.md §4
     */
    public function jalankanPerhitungan(): void
    {
        $this->authorize('create', CkpnIndividualResult::class);

        $this->confirmingAction = '';
        $periode = $this->individualPeriod;

        $userId = auth()->id();
        $dispatched = 0;

        $runningUsageTypes = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnIndividual->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->pluck('usage_type')
            ->all();

        foreach (UsageType::cases() as $usageType) {
            if (in_array($usageType->value, $runningUsageTypes, true)) {
                continue;
            }

            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::CkpnIndividual,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => $userId,
            ]);

            CkpnIndividualCalculationJob::dispatch($runLog->id, $usageType->value, $periode);
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->dispatch('notify', type: 'warning', message: 'Perhitungan untuk periode '.$periode.' sudah berjalan atau sedang diproses.');
        } else {
            $this->dispatch('notify', type: 'success', message: $dispatched.' job CKPN Individual berhasil diantrikan untuk periode '.$periode.'.');
            $this->isRunning = true;
        }
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedIds = CkpnIndividualResult::query()
                ->select('id')
                ->when($this->filterPeriode !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriode))
                ->limit(500)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->deletingId = $id;
        $this->confirmingAction = 'hapus';
    }

    public function hapus(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $this->authorize('delete', CkpnIndividualResult::findOrFail($this->deletingId));

        try {
            CkpnIndividualResult::findOrFail($this->deletingId)->delete();
            $this->dispatch('notify', type: 'success', message: 'Baris berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }

        $this->deletingId = null;
        $this->confirmingAction = '';
        $this->resetPage();
        $this->dispatch('$refresh');
    }

    public function batalHapus(): void
    {
        $this->deletingId = null;
        $this->confirmingAction = '';
    }

    public function konfirmasiBulkHapus(): void
    {
        if (count($this->selectedIds) === 0) {
            return;
        }
        $this->confirmingAction = 'bulk_hapus';
    }

    public function bulkHapus(): void
    {
        if (count($this->selectedIds) === 0) {
            $this->confirmingAction = '';

            return;
        }

        $this->authorize('deleteAny', CkpnIndividualResult::class);

        $deleted = 0;
        $errors = 0;

        foreach ($this->selectedIds as $id) {
            try {
                CkpnIndividualResult::findOrFail((int) $id)->delete();
                $deleted++;
            } catch (\RuntimeException) {
                $errors++;
            }
        }

        $this->flashMessage = $deleted.' baris dihapus'.($errors > 0 ? ', '.$errors.' gagal (status Completed).' : '.');
        $this->flashType = $errors > 0 ? 'error' : 'success';

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->confirmingAction = '';
        $this->resetPage();
        $this->dispatch('$refresh');
    }

    public function batalBulkHapus(): void
    {
        $this->confirmingAction = '';
    }

    /** Tampilkan dialog konfirmasi sebelum hapus semua data periode terpilih. */
    public function konfirmasiHapusPeriode(): void
    {
        if ($this->filterPeriode === '') {
            $this->dispatch('notify', type: 'error', message: 'Tampilkan data periode terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'hapus_periode';
    }

    /** Hapus seluruh baris CkpnIndividualResult untuk periode yang sedang ditampilkan. */
    public function hapusPeriode(): void
    {
        $this->authorize('deleteAny', CkpnIndividualResult::class);

        $this->confirmingAction = '';

        $deleted = CkpnIndividualResult::where('calculation_period', $this->filterPeriode)->delete();

        $this->showTable = false;
        $this->filterPeriode = '';
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->resetPage();

        $this->dispatch('notify', type: 'success', message: $deleted.' baris CKPN Individual untuk periode '.$this->individualPeriod.' berhasil dihapus.');
    }

    public function render(): View
    {
        $selectedPeriod = $this->individualPeriod !== ''
            ? CkpnPeriod::where('period', $this->individualPeriod)->first()
            : null;
        $periodClassified = (bool) ($selectedPeriod?->is_classified ?? false);
        $periodCalculated = $this->showTable
            ? CkpnIndividualResult::where('calculation_period', $this->filterPeriode)->exists()
            : false;

        $results = $this->showTable
            ? CkpnIndividualResult::query()
                ->with(['financingAccount'])
                ->where('calculation_period', $this->filterPeriode)
                ->when($this->search, fn ($q) => $q->where(
                    fn ($w) => $w
                        ->where('calculation_period', 'like', "%{$this->search}%")
                        ->orWhereHas('financingAccount', fn ($fa) => $fa->where('customer_name', 'like', "%{$this->search}%"))
                ))
                ->orderBy('financing_account_id')
                ->paginate(25)
            : CkpnIndividualResult::query()->whereRaw('1=0')->paginate(25);

        $totalCkpn = $this->showTable
            ? (float) CkpnIndividualResult::where('calculation_period', $this->filterPeriode)->sum('ckpn_amount')
            : 0.0;

        $totalOutstanding = $this->showTable
            ? (float) CkpnIndividualResult::where('calculation_period', $this->filterPeriode)->sum('outstanding_balance')
            : 0.0;

        $runLogs = $this->isRunning && $this->individualPeriod !== ''
            ? CalculationRunLog::where('period', $this->individualPeriod)
                ->where('run_type', RunType::CkpnIndividual->value)
                ->orderBy('usage_type')
                ->get()
            : collect();

        $periods = CkpnPeriod::query()->orderByDesc('period')->pluck('period');

        return view('livewire.ckpn.ckpn-individual-result-index', [
            'results' => $results,
            'runLogs' => $runLogs,
            'periods' => $periods,
            'totalCkpn' => $totalCkpn,
            'totalOutstanding' => $totalOutstanding,
            'periodClassified' => $periodClassified,
            'periodCalculated' => $periodCalculated,
        ]);
    }
}
