<?php

declare(strict_types=1);

namespace App\Livewire\Lgd;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Exports\LgdCollateralShortfallExport;
use App\Exports\LgdCsSourceExport;
use App\Jobs\LgdCsCalculationJob;
use App\Models\CalculationRunLog;
use App\Models\LgdCollateralShortfallBySegmentResult;
use App\Models\LgdCollateralShortfallResult;
use App\Services\LgdCsDetailService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman tabel hasil LGD Collateral Shortfall + trigger perhitungan.
 * Ref: PRD Bab 10
 */
#[Layout('layouts.app', ['title' => 'Hasil LGD Collateral Shortfall'])]
class LgdCsResultIndex extends Component
{
    use WithPagination;

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url]
    public string $search = '';

    public string $runPeriode = '';

    public string $runUsageTypePivot = '';

    public bool $isRunning = false;

    /** Aksi yang menunggu konfirmasi: 'hitung' | 'rekalkulasi' | 'hapus' | '' */
    public string $confirmingAction = '';

    /** true jika periode $runPeriode sudah pernah dihitung (ada di lgd_collateral_shortfall_result) */
    public bool $runPeriodeHasResult = false;

    /** true setelah user klik Tampilkan Data atau setelah perhitungan selesai di-dispatch */
    public bool $showResults = false;

    // Pivot detail on-the-fly
    public bool $pivotLoaded = false;

    public string $pivotError = '';

    public array $pivotData = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUsageType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPeriode(): void
    {
        $this->resetPage();
    }

    public function updatedRunPeriode(): void
    {
        $this->showResults = false;
        $this->runPeriodeHasResult = $this->runPeriode !== ''
            && LgdCollateralShortfallResult::where('calculation_period', $this->runPeriode)->exists();

        $this->pivotLoaded = false;
        $this->pivotData = [];
        $this->pivotError = '';
    }

    /**
     * Set filter periode ke runPeriode agar tabel hasil langsung menampilkan data
     * periode yang dipilih — tanpa redirect/reload halaman.
     */
    public function tampilkanData(): void
    {
        $this->filterPeriode = $this->runPeriode;
        $this->showResults = true;
        $this->resetPage();
    }

    /**
     * Hitung pivot detail LGD CS on-the-fly tanpa menyimpan snapshot.
     * Ref: PRD Bab 10
     */
    public function loadPivot(): void
    {
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->pivotLoaded = false;
            $this->pivotError = 'Pilih periode terlebih dahulu.';

            return;
        }

        try {
            $usageTypeValue = $this->runUsageTypePivot !== '' ? $this->runUsageTypePivot : null;
            $this->pivotData = (new LgdCsDetailService)->calculate($periode, $usageTypeValue);
            $this->pivotLoaded = true;
            $this->pivotError = '';
        } catch (\Throwable $e) {
            $this->pivotLoaded = false;
            $this->pivotData = [];
            $this->pivotError = 'Gagal memuat detail: '.$e->getMessage();
        }
    }

    /** Tampilkan dialog konfirmasi sebelum jalankan perhitungan. */
    public function confirmJalankan(): void
    {
        if ($this->runPeriode === '' || ! preg_match('/^\d{6}$/', trim($this->runPeriode))) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode perhitungan terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'hitung';
    }

    /** Tampilkan dialog konfirmasi sebelum re-kalkulasi. */
    public function confirmRekalkulasi(): void
    {
        if ($this->runPeriode === '' || ! preg_match('/^\d{6}$/', trim($this->runPeriode))) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode perhitungan terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'rekalkulasi';
    }

    /** Tampilkan dialog konfirmasi sebelum hapus perhitungan. */
    public function confirmHapus(): void
    {
        if ($this->runPeriode === '' || ! preg_match('/^\d{6}$/', trim($this->runPeriode))) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode yang valid terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'hapus';
    }

    /**
     * Dispatch LgdCsCalculationJob untuk semua UsageType pada periode terpilih.
     * Ref: PRD Bab 10, AGENTS.md §4 (idempotent)
     */
    public function jalankanPerhitungan(): void
    {
        $this->authorize('create', LgdCollateralShortfallResult::class);

        $this->confirmingAction = '';
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid. Gunakan format yyyymm (contoh: 202412).');

            return;
        }

        $userId = auth()->id();
        $dispatched = 0;

        // Tolak jika periode sudah Completed/Approved — gunakan Re-Kalkulasi
        $doneCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdCs->value)
            ->whereIn('status', [RunStatus::Completed->value, RunStatus::Approved->value])
            ->count();

        if ($doneCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah pernah dihitung. Gunakan tombol Rekalkulasi untuk menghitung ulang.");

            return;
        }

        // 1 query untuk semua UsageType yang sedang pending/processing (hindari N+1)
        $runningUsageTypes = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdCs->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->pluck('usage_type')
            ->all();

        foreach (UsageType::cases() as $usageType) {
            if (in_array($usageType->value, $runningUsageTypes, true)) {
                continue;
            }

            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::LgdCs,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => $userId,
            ]);

            LgdCsCalculationJob::dispatch($runLog->id, $usageType->value, $periode);
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->dispatch('notify', type: 'warning', message: 'Perhitungan untuk periode '.$periode.' sudah berjalan atau sedang diproses.');
        } else {
            $this->dispatch('notify', type: 'success', message: $dispatched.' job LGD-CS berhasil diantrikan untuk periode '.$periode.'.');
        }

        $this->isRunning = true;
        $this->showResults = true;
    }

    /**
     * Re-kalkulasi: buat runLog baru lalu dispatch ulang job.
     * Ref: PRD Bab 10, FR-13 (snapshot immutability — hanya boleh re-run jika tidak Approved)
     */
    public function rekalkulasi(): void
    {
        $this->authorize('create', LgdCollateralShortfallResult::class);

        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid. Gunakan format yyyymm (contoh: 202412).');

            return;
        }

        // Tolak re-run jika ada status Approved (snapshot sudah disetujui)
        $approvedCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdCs->value)
            ->where('status', RunStatus::Approved->value)
            ->count();

        if ($approvedCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah Approved. Rekalkulasi tidak diizinkan.");

            return;
        }

        // Tolak jika masih ada yang sedang Pending/Processing
        $runningCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdCs->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        if ($runningCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} masih sedang diproses. Tunggu hingga selesai.");

            return;
        }

        // Hapus snapshot hasil periode tsb (run log lama tetap ada — history terjaga,
        // konsisten dengan LgdFinalResultIndex::rekalkulasiPerhitungan)
        DB::transaction(function () use ($periode): void {
            DB::table('lgd_collateral_shortfall_by_segment_result')
                ->where('calculation_period', $periode)->delete();
            DB::table('lgd_collateral_shortfall_result')
                ->where('calculation_period', $periode)->delete();
        });

        $userId = auth()->id();
        $dispatched = 0;

        foreach (UsageType::cases() as $usageType) {
            // Buat runLog baru untuk re-run (bukan update yg lama, agar history terjaga)
            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::LgdCs,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => $userId,
            ]);

            LgdCsCalculationJob::dispatch($runLog->id, $usageType->value, $periode);
            $dispatched++;
        }

        $this->runPeriode = $periode;
        $this->isRunning = true;
        $this->showResults = true;
        $this->dispatch('notify', type: 'success', message: "Rekalkulasi dispatched {$dispatched} job untuk periode {$periode}.");
    }

    /** Hapus seluruh snapshot LGD Collateral Shortfall berdasarkan periode terpilih. */
    public function hapusPerhitungan(): void
    {
        $this->authorize('deleteAny', LgdCollateralShortfallResult::class);

        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode yang valid terlebih dahulu.');

            return;
        }

        $runLogIds = CalculationRunLog::query()
            ->where('period', $periode)
            ->where('run_type', RunType::LgdCs->value)
            ->pluck('id');

        $hasResultData = DB::table('lgd_collateral_shortfall_result')
            ->where('calculation_period', $periode)->exists();

        if ($runLogIds->isEmpty() && ! $hasResultData) {
            $this->dispatch('notify', type: 'error', message: "Tidak ada perhitungan LGD Collateral Shortfall untuk periode {$periode}.");

            return;
        }

        if ($runLogIds->isNotEmpty()) {
            $approvedCount = CalculationRunLog::query()
                ->whereIn('id', $runLogIds)
                ->where('status', RunStatus::Approved->value)
                ->count();

            if ($approvedCount > 0) {
                $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah Approved dan tidak dapat dihapus.");

                return;
            }
        }

        $this->deletePeriodeData($periode);

        $this->redirect(route('kalkulasi.lgd.index'), navigate: true);
    }

    /**
     * Hapus seluruh snapshot LGD Collateral Shortfall (run log + data hasil) untuk periode tertentu.
     * Dipakai oleh hapusPerhitungan() dan sebelum rekalkulasi agar tidak terjadi akumulasi data lama.
     */
    private function deletePeriodeData(string $periode): void
    {
        $runLogIds = CalculationRunLog::query()
            ->where('period', $periode)
            ->where('run_type', RunType::LgdCs->value)
            ->pluck('id');

        DB::transaction(function () use ($runLogIds, $periode): void {
            if ($runLogIds->isNotEmpty()) {
                DB::table('lgd_collateral_shortfall_by_segment_result')
                    ->whereIn('calculation_run_log_id', $runLogIds)
                    ->delete();

                DB::table('lgd_collateral_shortfall_result')
                    ->whereIn('calculation_run_log_id', $runLogIds)
                    ->delete();

                CalculationRunLog::query()->whereIn('id', $runLogIds)->delete();
            }

            // Fallback: hapus sisa data orphan berdasarkan periode
            DB::table('lgd_collateral_shortfall_by_segment_result')
                ->where('calculation_period', $periode)->delete();
            DB::table('lgd_collateral_shortfall_result')
                ->where('calculation_period', $periode)->delete();
        });
    }

    /**
     * Export snapshot LGD CS sesuai filter periode & jenis penggunaan yang sedang ditampilkan.
     */
    public function exportExcel(): BinaryFileResponse
    {
        $period = trim($this->filterPeriode);
        $usageTypeValue = $this->filterUsageType !== '' ? (int) $this->filterUsageType : null;

        $suffix = $period !== '' ? "-{$period}" : '';

        return Excel::download(
            new LgdCollateralShortfallExport($period, $usageTypeValue),
            "lgd-collateral-shortfall{$suffix}.xlsx",
        );
    }

    /** Export data sumber nasabah LGD CS sesuai filter aktif ke Excel. */
    public function exportSourceExcel(): BinaryFileResponse
    {
        $suffix = $this->filterPeriode !== '' ? '-'.$this->filterPeriode : '';
        $suffix .= $this->filterUsageType !== '' ? '-'.$this->filterUsageType : '';

        return Excel::download(
            new LgdCsSourceExport($this->filterPeriode, $this->filterUsageType, $this->search),
            "nasabah-lgd-cs{$suffix}.xlsx",
        );
    }

    /** Polling status job aktif untuk periode runPeriode. */
    public function pollJobStatus(): void
    {
        if ($this->runPeriode === '') {
            return;
        }

        $activeCount = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::LgdCs->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        $wasRunning = $this->isRunning;
        $this->isRunning = $activeCount > 0;

        // Saat job baru selesai, refresh status hasil dan tampilkan data
        if ($wasRunning && ! $this->isRunning) {
            $this->runPeriodeHasResult = LgdCollateralShortfallResult::where('calculation_period', $this->runPeriode)->exists();
            if ($this->runPeriodeHasResult) {
                $this->filterPeriode = $this->runPeriode;
                $this->showResults = true;
            }
        }
    }

    public function render(): View
    {
        $results = $this->showResults
            ? LgdCollateralShortfallResult::query()
                ->with(['calculationRunLog', 'financingAccount'])
                ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
                ->when($this->filterPeriode !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriode))
                ->when($this->search, fn ($q) => $q->where(
                    fn ($w) => $w
                        ->where('calculation_period', 'like', "%{$this->search}%")
                        ->orWhere('usage_type', 'like', "%{$this->search}%")
                        ->orWhereHas('financingAccount', fn ($fa) => $fa->where('financing_code', 'like', "%{$this->search}%"))
                ))
                ->orderByDesc('calculation_period')
                ->orderBy('usage_type')
                ->paginate(25)
            : LgdCollateralShortfallResult::query()->whereRaw('0=1')->paginate(25);

        // Snapshot agregat per segmen — satu baris per segmen per periode (tanpa pagination, jumlah kecil)
        $aggregates = $this->showResults
            ? LgdCollateralShortfallBySegmentResult::query()
                ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
                ->when($this->filterPeriode !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriode))
                ->orderByDesc('calculation_period')
                ->orderBy('usage_type')
                ->get()
            : collect();

        $runLogs = [];
        if ($this->runPeriode !== '') {
            $runLogs = CalculationRunLog::where('period', $this->runPeriode)
                ->where('run_type', RunType::LgdCs->value)
                ->orderBy('usage_type')
                ->get();
        }

        $service = new LgdCsDetailService;

        return view('livewire.lgd.lgd-cs-result-index', [
            'results' => $results,
            'aggregates' => $aggregates,
            'usageTypes' => UsageType::cases(),
            'runLogs' => $runLogs,
            'availablePeriods' => $service->availablePeriods(),
            'snapshotPeriods' => $service->snapshotPeriods(),
        ]);
    }
}
