<?php

declare(strict_types=1);

namespace App\Livewire\Lgd;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Exports\LgdErSourceExport;
use App\Exports\LgdExpectedRecoveriesExport;
use App\Jobs\LgdErCalculationJob;
use App\Models\CalculationRunLog;
use App\Models\LgdExpectedRecoveriesResult;
use App\Services\LgdErDetailService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman tabel hasil LGD Expected Recoveries + trigger perhitungan + pivot detail.
 * Ref: PRD Bab 9
 */
#[Layout('layouts.app', ['title' => 'Hasil LGD Expected Recoveries'])]
class LgdErResultIndex extends Component
{
    use WithPagination;

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url]
    public string $search = '';

    // Panel jalankan perhitungan
    public string $runPeriode = '';

    public string $runUsageTypePivot = '';  // untuk pivot detail

    public bool $isRunning = false;

    /** Aksi yang menunggu konfirmasi: 'hitung' | 'rekalkulasi' | 'hapus' | '' */
    public string $confirmingAction = '';

    /** true jika periode $runPeriode sudah pernah dihitung (ada di lgd_expected_recoveries_result) */
    public bool $runPeriodeHasResult = false;

    /** true setelah user klik Tampilkan Data atau setelah perhitungan selesai di-dispatch */
    public bool $showResults = false;

    // Data pivot detail on-the-fly
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
            && LgdExpectedRecoveriesResult::where('calculation_period', $this->runPeriode)->exists();

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
     * Dispatch LgdErCalculationJob untuk semua UsageType pada periode terpilih.
     * Ref: PRD Bab 9, AGENTS.md §4 (idempotent)
     */
    public function jalankanPerhitungan(): void
    {
        $this->confirmingAction = '';
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode perhitungan terlebih dahulu.');

            return;
        }

        $userId = auth()->id();
        $dispatched = 0;

        // Tolak jika periode sudah Completed/Approved — gunakan Re-Kalkulasi
        $doneCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdEr->value)
            ->whereIn('status', [RunStatus::Completed->value, RunStatus::Approved->value])
            ->count();

        if ($doneCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah pernah dihitung. Gunakan tombol Rekalkulasi untuk menghitung ulang.");

            return;
        }

        // 1 query untuk semua UsageType yang sedang pending/processing (hindari N+1)
        $runningUsageTypes = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdEr->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->pluck('usage_type')
            ->all();

        foreach (UsageType::cases() as $usageType) {
            if (in_array($usageType->value, $runningUsageTypes, true)) {
                continue;
            }

            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::LgdEr,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => $userId,
            ]);

            LgdErCalculationJob::dispatch($runLog->id, $usageType->value, $periode);
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->dispatch('notify', type: 'warning', message: "Perhitungan untuk periode {$periode} sudah berjalan atau sedang diproses.");
        } else {
            $this->dispatch('notify', type: 'success', message: "Dispatched {$dispatched} job perhitungan LGD Expected Recoveries untuk periode {$periode}.");
        }

        $this->isRunning = true;
        $this->showResults = true;
    }

    /**
     * Re-kalkulasi: buat runLog baru lalu dispatch ulang job.
     * Ref: PRD Bab 9, FR-13 (snapshot immutability — hanya boleh re-run jika tidak Approved)
     */
    public function rekalkulasi(): void
    {
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode perhitungan terlebih dahulu.');

            return;
        }

        // Tolak re-run jika ada status Approved (snapshot sudah disetujui)
        $approvedCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdEr->value)
            ->where('status', RunStatus::Approved->value)
            ->count();

        if ($approvedCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah Approved. Rekalkulasi tidak diizinkan.");

            return;
        }

        // Tolak jika masih ada yang sedang Pending/Processing
        $runningCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::LgdEr->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        if ($runningCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} masih sedang diproses. Tunggu hingga selesai.");

            return;
        }

        // Hapus snapshot periode tsb terlebih dahulu agar tidak terjadi akumulasi data lama + baru
        $this->deletePeriodeData($periode);

        $userId = auth()->id();
        $dispatched = 0;

        foreach (UsageType::cases() as $usageType) {
            // Buat runLog baru untuk re-run (bukan update yg lama, agar history terjaga)
            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::LgdEr,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => $userId,
            ]);

            LgdErCalculationJob::dispatch($runLog->id, $usageType->value, $periode);
            $dispatched++;
        }

        $this->runPeriode = $periode;
        $this->isRunning = true;
        $this->showResults = true;
        $this->dispatch('notify', type: 'success', message: "Rekalkulasi dispatched {$dispatched} job untuk periode {$periode}.");
    }

    /** Hapus seluruh snapshot LGD Expected Recoveries berdasarkan periode terpilih. */
    public function hapusPerhitungan(): void
    {
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode yang valid terlebih dahulu.');

            return;
        }

        $runLogIds = CalculationRunLog::query()
            ->where('period', $periode)
            ->where('run_type', RunType::LgdEr->value)
            ->pluck('id');

        // Cek apakah ada data hasil — bisa jadi run log tidak ada tapi data snapshot ada
        $hasResultData = LgdExpectedRecoveriesResult::where('calculation_period', $periode)->exists();

        if ($runLogIds->isEmpty() && ! $hasResultData) {
            $this->dispatch('notify', type: 'error', message: "Tidak ada perhitungan LGD Expected Recoveries untuk periode {$periode}.");

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
     * Hapus seluruh snapshot LGD Expected Recoveries (run log + data hasil) untuk periode tertentu.
     * Dipakai oleh hapusPerhitungan() dan sebelum rekalkulasi agar tidak terjadi akumulasi data lama.
     */
    private function deletePeriodeData(string $periode): void
    {
        $runLogIds = CalculationRunLog::query()
            ->where('period', $periode)
            ->where('run_type', RunType::LgdEr->value)
            ->pluck('id');

        DB::transaction(function () use ($runLogIds, $periode): void {
            // Hapus via run log jika ada
            if ($runLogIds->isNotEmpty()) {
                DB::table('lgd_expected_recoveries_result')
                    ->whereIn('calculation_run_log_id', $runLogIds)
                    ->delete();

                CalculationRunLog::query()->whereIn('id', $runLogIds)->delete();
            }

            // Fallback: hapus sisa data orphan berdasarkan periode
            LgdExpectedRecoveriesResult::where('calculation_period', $periode)->delete();
        });
    }

    /**
     * Export snapshot LGD ER sesuai filter periode & jenis penggunaan yang sedang ditampilkan.
     */
    public function exportExcel(): BinaryFileResponse
    {
        $period = trim($this->filterPeriode);
        $usageTypeValue = $this->filterUsageType !== '' ? (int) $this->filterUsageType : null;

        $suffix = $period !== '' ? "-{$period}" : '';

        return Excel::download(
            new LgdExpectedRecoveriesExport($period, $usageTypeValue),
            "lgd-expected-recoveries{$suffix}.xlsx",
        );
    }

    /** Export data sumber nasabah LGD ER sesuai filter aktif ke Excel. */
    public function exportSourceExcel(): BinaryFileResponse
    {
        $suffix = $this->filterPeriode !== '' ? '-'.$this->filterPeriode : '';
        $suffix .= $this->filterUsageType !== '' ? '-'.$this->filterUsageType : '';

        return Excel::download(
            new LgdErSourceExport($this->filterPeriode, $this->filterUsageType, $this->search),
            "nasabah-lgd-er{$suffix}.xlsx",
        );
    }

    /** Polling status job aktif untuk periode runPeriode. */
    public function pollJobStatus(): void
    {
        if ($this->runPeriode === '') {
            return;
        }

        $activeCount = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::LgdEr->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        $wasRunning = $this->isRunning;
        $this->isRunning = $activeCount > 0;

        // Saat job baru selesai, refresh status hasil dan tampilkan data
        if ($wasRunning && ! $this->isRunning) {
            $this->runPeriodeHasResult = LgdExpectedRecoveriesResult::where('calculation_period', $this->runPeriode)->exists();
            if ($this->runPeriodeHasResult) {
                $this->filterPeriode = $this->runPeriode;
                $this->showResults = true;
            }
        }
    }

    /** Muat pivot detail on-the-fly untuk periode + usage type terpilih. */
    public function loadPivot(): void
    {
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->pivotLoaded = false;
            $this->pivotError = 'Pilih periode perhitungan terlebih dahulu.';

            return;
        }

        try {
            $service = new LgdErDetailService;
            $usageTypeValue = $this->runUsageTypePivot !== '' ? $this->runUsageTypePivot : null;
            $this->pivotData = $service->calculate($periode, $usageTypeValue);
            $this->pivotData['debtors'] = $service->debtorList($periode, $usageTypeValue)->toArray();
            $this->pivotLoaded = true;
            $this->pivotError = '';
        } catch (\Throwable $e) {
            $this->pivotLoaded = false;
            $this->pivotData = [];
            $this->pivotError = 'Gagal memuat detail: '.$e->getMessage();
        }
    }

    public function render(): View
    {
        $results = $this->showResults
            ? LgdExpectedRecoveriesResult::query()
                ->with(['calculationRunLog'])
                ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
                ->when($this->filterPeriode !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriode))
                ->when($this->search, fn ($q) => $q->where(
                    fn ($w) => $w
                        ->where('calculation_period', 'like', "%{$this->search}%")
                        ->orWhere('usage_type', 'like', "%{$this->search}%")
                ))
                ->orderByDesc('calculation_period')
                ->orderBy('usage_type')
                ->paginate(25)
            : LgdExpectedRecoveriesResult::query()->whereRaw('0=1')->paginate(25);

        $runLogs = [];
        if ($this->runPeriode !== '') {
            $runLogs = CalculationRunLog::where('period', $this->runPeriode)
                ->where('run_type', RunType::LgdEr->value)
                ->orderBy('usage_type')
                ->get();
        }

        $service = new LgdErDetailService;

        return view('livewire.lgd.lgd-er-result-index', [
            'results' => $results,
            'usageTypes' => UsageType::cases(),
            'runLogs' => $runLogs,
            'availablePeriods' => $service->availablePeriods(),
            'snapshotPeriods' => $service->snapshotPeriods(),
        ]);
    }
}
