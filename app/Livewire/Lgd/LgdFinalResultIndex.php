<?php

declare(strict_types=1);

namespace App\Livewire\Lgd;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\LgdFinalCalculationJob;
use App\Models\CalculationRunLog;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\LgdFinalResult;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman hasil LGD Final per segmen (gabungan ER + CS) + trigger perhitungan.
 * Ref: PRD Bab 11
 */
#[Layout('layouts.app', ['title' => 'Hasil LGD Final'])]
class LgdFinalResultIndex extends Component
{
    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url]
    public string $search = '';

    public string $runPeriode = '';

    public bool $isRunning = false;

    /** ID run log terkecil dari batch terbaru (dipakai pollJobStatus agar tidak tercampur batch lama). */
    public int $currentBatchMinRunLogId = 0;

    /** Aksi yang menunggu konfirmasi: 'hitung' | 'rekalkulasi' | 'hapus' | '' */
    public string $confirmingAction = '';

    /** true jika periode $runPeriode sudah pernah dihitung */
    public bool $runPeriodeHasResult = false;

    /** true setelah user klik Tampilkan Data atau setelah perhitungan selesai di-dispatch */
    public bool $showResults = false;

    public function mount(): void
    {
        // Sinkronisasi state dari DB saat komponen pertama dimuat (tahan refresh halaman)
        $this->syncPeriodeState();
    }

    public function updatedFilterPeriode(): void
    {
        // filter tidak perlu tindakan tambahan (view reactive)
    }

    public function updatedRunPeriode(): void
    {
        $this->showResults = false;
        $this->syncPeriodeState();
    }

    /** Sinkronisasi state isRunning, runPeriodeHasResult, dan currentBatchMinRunLogId dari DB (agar tahan page refresh). */
    private function syncPeriodeState(): void
    {
        if ($this->runPeriode === '') {
            $this->runPeriodeHasResult = false;
            $this->isRunning = false;
            $this->currentBatchMinRunLogId = 0;

            return;
        }

        $this->runPeriodeHasResult = LgdFinalResult::where('calculation_period', $this->runPeriode)->exists();

        // Cek apakah masih ada job yang sedang berjalan (Pending/Processing)
        $activeLogs = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::LgdFinal->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->get(['id']);

        $this->isRunning = $activeLogs->isNotEmpty();

        // Jika ada job aktif dan currentBatchMinRunLogId belum di-set (misal setelah page refresh),
        // gunakan ID minimum dari job aktif sebagai batas batch terbaru.
        if ($this->isRunning && $this->currentBatchMinRunLogId === 0) {
            $this->currentBatchMinRunLogId = $activeLogs->min('id') ?? 0;
        }
    }

    public function tampilkanData(): void
    {
        $this->filterPeriode = $this->runPeriode;
        $this->showResults = true;
    }

    public function confirmJalankan(): void
    {
        $this->confirmingAction = 'hitung';
    }

    public function confirmRekalkulasi(): void
    {
        $this->confirmingAction = 'rekalkulasi';
    }

    public function confirmHapus(): void
    {
        $this->confirmingAction = 'hapus';
    }

    public function jalankanPerhitungan(): void
    {
        $this->confirmingAction = '';

        if ($this->runPeriode === '') {
            return;
        }

        $this->dispatchJobs();
    }

    public function rekalkulasiPerhitungan(): void
    {
        $this->confirmingAction = '';

        if ($this->runPeriode === '') {
            return;
        }

        // Tolak jika ada run log berstatus Approved (Ref: PRD Bab 13.2 / FR-13)
        $hasApproved = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::LgdFinal->value)
            ->where('status', RunStatus::Approved)
            ->exists();

        if ($hasApproved) {
            return;
        }

        // Hapus snapshot periode tsb (via run log + fallback orphan by periode) sebelum rekalkulasi
        $runLogIds = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::LgdFinal->value)
            ->pluck('id');

        LgdFinalResult::whereIn('calculation_run_log_id', $runLogIds)->delete();
        LgdFinalResult::where('calculation_period', $this->runPeriode)->delete();

        $this->showResults = false;
        $this->dispatchJobs();
    }

    public function hapusPerhitungan(): void
    {
        $this->confirmingAction = '';

        if ($this->runPeriode === '') {
            return;
        }

        DB::transaction(function (): void {
            $runLogIds = CalculationRunLog::where('period', $this->runPeriode)
                ->where('run_type', RunType::LgdFinal->value)
                ->pluck('id');

            LgdFinalResult::whereIn('calculation_run_log_id', $runLogIds)->delete();
            CalculationRunLog::whereIn('id', $runLogIds)->delete();
        });

        $this->runPeriodeHasResult = false;
        $this->showResults = false;
        $this->filterPeriode = '';

        $this->redirect(route('kalkulasi.lgd.index'), navigate: true);
    }

    public function pollJobStatus(): void
    {
        if ($this->runPeriode === '') {
            return;
        }

        // Re-sync isRunning dari DB agar tahan refresh halaman (property Livewire tidak persisten)
        $this->syncPeriodeState();

        if (! $this->isRunning) {
            return;
        }

        // Filter hanya ke batch terbaru (id >= currentBatchMinRunLogId) agar run log
        // lama yang berstatus failed tidak membuat allDone = true secara prematur.
        $query = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::LgdFinal->value)
            ->whereNotIn('status', [RunStatus::Completed->value, RunStatus::Failed->value]);

        if ($this->currentBatchMinRunLogId > 0) {
            $query->where('id', '>=', $this->currentBatchMinRunLogId);
        }

        $allDone = $query->doesntExist();

        if ($allDone) {
            $this->isRunning = false;
            $this->runPeriodeHasResult = LgdFinalResult::where('calculation_period', $this->runPeriode)->exists();
            $this->showResults = $this->runPeriodeHasResult;
            $this->filterPeriode = $this->runPeriode;
        }
    }

    private function dispatchJobs(): void
    {
        $this->isRunning = true;
        $this->currentBatchMinRunLogId = 0;

        $firstId = null;
        foreach (UsageType::cases() as $usageType) {
            $runLog = CalculationRunLog::create([
                'run_type' => RunType::LgdFinal->value,
                'period' => $this->runPeriode,
                'usage_type' => $usageType->value,
                'status' => RunStatus::Pending->value,
            ]);

            if ($firstId === null) {
                $firstId = $runLog->id;
            }

            LgdFinalCalculationJob::dispatch($runLog->id, $usageType->value, $this->runPeriode);
        }

        // Simpan ID minimum batch ini agar pollJobStatus tidak tercampur run log lama yang failed
        $this->currentBatchMinRunLogId = $firstId ?? 0;
    }

    /** Daftar periode yang tersedia dari snapshot LGD ER (sumber data LGD Final). */
    private function availablePeriods(): array
    {
        return LgdExpectedRecoveriesResult::select('calculation_period')
            ->distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period')
            ->toArray();
    }

    /** Daftar periode yang sudah ada snapshot LGD Final. */
    private function snapshotPeriods(): array
    {
        return LgdFinalResult::select('calculation_period')
            ->distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period')
            ->toArray();
    }

    public function render(): View
    {
        $results = $this->showResults
            ? LgdFinalResult::with('calculationRunLog')
                ->when($this->filterPeriode !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriode))
                ->when($this->search, fn ($q) => $q->where(
                    fn ($w) => $w
                        ->where('calculation_period', 'like', "%{$this->search}%")
                        ->orWhere('usage_type', 'like', "%{$this->search}%")
                ))
                ->orderByDesc('calculation_period')
                ->orderBy('usage_type')
                ->get()
            : collect();

        $runLogs = [];
        if ($this->runPeriode !== '') {
            $runLogs = CalculationRunLog::where('period', $this->runPeriode)
                ->where('run_type', RunType::LgdFinal->value)
                ->orderBy('usage_type')
                ->get();
        }

        return view('livewire.lgd.lgd-final-result-index', [
            'results' => $results,
            'usageTypes' => UsageType::cases(),
            'runLogs' => $runLogs,
            'availablePeriods' => $this->availablePeriods(),
            'snapshotPeriods' => $this->snapshotPeriods(),
        ]);
    }
}
