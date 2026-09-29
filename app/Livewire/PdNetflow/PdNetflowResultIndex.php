<?php

declare(strict_types=1);

namespace App\Livewire\PdNetflow;

use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\CalculationDispatchService;
use App\Enums\PdMethod;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Exports\PdNetflowSourceExport;
use App\Jobs\PdNetflowCalculationJob;
use App\Models\CalculationGeneralSetting;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\PdNetflowConsolidated;
use App\Models\PdNetflowInvestasi;
use App\Models\PdNetflowKonsumsi;
use App\Models\PdNetflowModalKerja;
use App\Models\PdNetflowResult;
use App\Services\PdNetflowDetailService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman tabel hasil PD Netflow + trigger perhitungan per periode.
 * Ref: PRD Bab 7
 */
#[Layout('layouts.app', ['title' => 'Hasil PD Netflow'])]
class PdNetflowResultIndex extends Component
{
    use WithPagination;

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url]
    public string $search = '';

    /** Periode yang sedang di-trigger untuk perhitungan */
    public string $runPeriode = '';

    public bool $isRunning = false;

    /** Aksi yang menunggu konfirmasi: 'hitung' | 'rekalkulasi' | 'hapus' | '' */
    public string $confirmingAction = '';

    /** true jika periode $runPeriode sudah pernah dihitung (ada di pd_netflow_result) */
    public bool $runPeriodeHasResult = false;

    /** true setelah user klik Tampilkan Data atau setelah perhitungan selesai di-dispatch */
    public bool $showResults = false;

    public function updatedRunPeriode(): void
    {
        $this->showResults = false;
        $this->runPeriodeHasResult = $this->runPeriode !== ''
            && PdNetflowResult::where('calculation_period', $this->runPeriode)->exists();
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

    /** Export data PD Netflow sesuai filter aktif ke Excel. */
    public function exportSourceExcel(): BinaryFileResponse
    {
        $filename = 'nasabah-pd-netflow'
            .($this->filterPeriode !== '' ? '-'.$this->filterPeriode : '')
            .($this->filterUsageType !== '' ? '-'.$this->filterUsageType : '')
            .'.xlsx';

        return Excel::download(
            new PdNetflowSourceExport($this->filterPeriode, $this->filterUsageType, $this->search),
            $filename,
        );
    }

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

    /**
     * Dispatch PdNetflowCalculationJob untuk semua UsageType pada periode yang dipilih.
     * Ref: PRD Bab 7, AGENTS.md §4 (idempotent via job)
     */
    /** Tampilkan dialog konfirmasi sebelum jalankan perhitungan. */
    public function confirmJalankan(): void
    {
        $periode = trim($this->runPeriode);
        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid. Gunakan format yyyymm (contoh: 202412).');

            return;
        }

        if (! $this->isAllowedPdMethod($periode)) {
            return;
        }

        $this->confirmingAction = 'hitung';
    }

    /** Tampilkan dialog konfirmasi sebelum re-kalkulasi. */
    public function confirmRekalkulasi(): void
    {
        $periode = trim($this->runPeriode);
        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid. Gunakan format yyyymm (contoh: 202412).');

            return;
        }

        if (! $this->isAllowedPdMethod($periode)) {
            return;
        }

        $this->confirmingAction = 'rekalkulasi';
    }

    /**
     * Validasi metode PD: mode single → hanya boleh PD Netflow jika pd_method periode = netflow.
     * Mode dual (allow_dual_pd_method=1) → kedua metode diizinkan.
     * Ref: AGENTS.md §6, PRD Bab 7
     */
    private function isAllowedPdMethod(string $periode): bool
    {
        $isDual = CalculationGeneralSetting::value('allow_dual_pd_method', '0') === '1';

        if ($isDual) {
            return true;
        }

        $period = CkpnPeriod::where('period', $periode)->first();

        if ($period === null) {
            // Periode belum ditetapkan — izinkan (belum ada constraint)
            return true;
        }

        if ($period->pd_method !== null && $period->pd_method !== PdMethod::Netflow) {
            $label = $period->pd_method->label();
            $this->dispatch('notify', type: 'error', message: "Periode {$periode} ditetapkan dengan metode {$label}. Gunakan halaman PD Migration untuk menghitung periode ini.");

            return false;
        }

        return true;
    }

    /** Tampilkan dialog konfirmasi sebelum hapus perhitungan. */
    public function confirmHapus(): void
    {
        $periode = trim($this->runPeriode);
        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode yang valid terlebih dahulu.');

            return;
        }

        $this->confirmingAction = 'hapus';
    }

    public function jalankanPerhitungan(): void
    {
        $this->authorize('create', PdNetflowResult::class);

        $this->confirmingAction = '';
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid. Gunakan format yyyymm (contoh: 202412).');

            return;
        }

        $userId = auth()->id();

        // Dispatch per (jenis penggunaan × target kantor × target akad) — Ref: PRD Bab 5 (segmentasi level 1 & 2)
        $result = DB::transaction(fn (): array => CalculationDispatchService::dispatchPerSegment(
            runType: RunType::PdNetflow,
            akadKey: AkadEligibilityService::KEY_PD_RATE,
            period: $periode,
            userId: $userId,
            dispatcher: fn (CalculationRunLog $runLog, UsageType $usageType, ?string $officeCode, ?string $akadCode) => PdNetflowCalculationJob::dispatch($runLog->id, $usageType->value, $periode, $officeCode, $akadCode),
        ));

        if ($result['dispatched'] === 0) {
            $this->dispatch('notify', type: 'warning', message: "Perhitungan untuk periode {$periode} sudah berjalan atau sedang diproses.");
        } else {
            $this->dispatch('notify', type: 'success', message: "Dispatched {$result['dispatched']} job perhitungan PD Netflow untuk periode {$periode} (konsolidasi + pecahan per kantor).");
        }

        $this->isRunning = true;
        $this->showResults = true;
    }

    /**
     * Re-kalkulasi: reset runLog yang sudah Completed/Failed lalu dispatch ulang job.
     * Ref: PRD Bab 7, FR-13 (snapshot immutability — hanya boleh re-run jika tidak Approved)
     */
    public function rekalkulasi(): void
    {
        $this->authorize('create', PdNetflowResult::class);

        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid.');

            return;
        }

        // Tolak re-run jika ada status Approved (snapshot sudah disetujui)
        $approvedCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::PdNetflow->value)
            ->where('status', RunStatus::Approved->value)
            ->count();

        if ($approvedCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah Approved. Re-kalkulasi tidak diizinkan.");

            return;
        }

        // Tolak jika masih ada yang sedang Pending/Processing
        $runningCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::PdNetflow->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        if ($runningCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} masih sedang diproses. Tunggu hingga selesai.");

            return;
        }

        $userId = auth()->id();

        // Re-run: selalu buat run log baru per (jenis penggunaan × target kantor × target akad)
        $result = CalculationDispatchService::dispatchPerSegment(
            runType: RunType::PdNetflow,
            akadKey: AkadEligibilityService::KEY_PD_RATE,
            period: $periode,
            userId: $userId,
            dispatcher: fn (CalculationRunLog $runLog, UsageType $usageType, ?string $officeCode, ?string $akadCode) => PdNetflowCalculationJob::dispatch($runLog->id, $usageType->value, $periode, $officeCode, $akadCode),
            forceRerun: true,
        );

        $this->runPeriode = $periode;
        $this->isRunning = true;
        $this->dispatch('notify', type: 'success', message: "Re-kalkulasi dispatched {$result['dispatched']} job untuk periode {$periode} (konsolidasi + pecahan per kantor).");
    }

    /** Hapus seluruh snapshot PD Netflow berdasarkan periode terpilih. */
    public function hapusPerhitungan(): void
    {
        $this->authorize('deleteAny', PdNetflowResult::class);

        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih periode yang valid terlebih dahulu.');

            return;
        }

        $runLogIds = CalculationRunLog::query()
            ->where('period', $periode)
            ->where('run_type', RunType::PdNetflow->value)
            ->pluck('id');

        // Cek apakah ada data hasil meski run log sudah tidak ada (orphan data)
        $hasOrphanData = $runLogIds->isEmpty()
            && PdNetflowResult::where('calculation_period', $periode)->exists();

        if ($runLogIds->isEmpty() && ! $hasOrphanData) {
            $this->dispatch('notify', type: 'error', message: "Tidak ada perhitungan PD Netflow untuk periode {$periode}.");

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

        DB::transaction(function () use ($runLogIds, $periode): void {
            if ($runLogIds->isNotEmpty()) {
                foreach (['pd_netflow_compound_rate', 'pd_netflow_bucket_movement', 'pd_netflow_result'] as $table) {
                    DB::table($table)->whereIn('calculation_run_log_id', $runLogIds)->delete();
                }
                CalculationRunLog::query()->whereIn('id', $runLogIds)->delete();
            } else {
                // Fallback: hapus orphan data langsung by periode (nama kolom berbeda per tabel)
                DB::table('pd_netflow_compound_rate')->where('start_period', $periode)->delete();
                DB::table('pd_netflow_bucket_movement')->where('period', $periode)->delete();
                DB::table('pd_netflow_result')->where('calculation_period', $periode)->delete();
            }
        });

        $this->redirect(route('kalkulasi.pd.index'), navigate: true);
    }

    /** Polling status job aktif untuk periode runPeriode. */
    public function pollJobStatus(): void
    {
        if ($this->runPeriode === '') {
            return;
        }

        $activeCount = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::PdNetflow->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        $wasRunning = $this->isRunning;
        $this->isRunning = $activeCount > 0;

        // Saat job baru selesai, refresh status hasil dan tampilkan data
        if ($wasRunning && ! $this->isRunning) {
            $this->runPeriodeHasResult = PdNetflowResult::where('calculation_period', $this->runPeriode)->exists();
            if ($this->runPeriodeHasResult) {
                $this->filterPeriode = $this->runPeriode;
                $this->showResults = true;
            }
        }
    }

    public function render(): View
    {
        // Re-check setiap render agar konsisten setelah hapus dari halaman lain
        if ($this->runPeriode !== '') {
            $this->runPeriodeHasResult = PdNetflowResult::where('calculation_period', $this->runPeriode)->exists();
            if (! $this->runPeriodeHasResult) {
                $this->showResults = false;
            }
        }

        $results = $this->showResults
            ? PdNetflowResult::query()
                ->with(['calculationRunLog', 'fromBucket'])
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
            : PdNetflowResult::query()->whereRaw('0=1')->paginate(25);

        $periods = CkpnPeriod::select('id', 'period')->orderByDesc('period')->pluck('period', 'id');

        $runLogs = [];
        if ($this->runPeriode !== '') {
            $runLogs = CalculationRunLog::where('period', $this->runPeriode)
                ->where('run_type', RunType::PdNetflow->value)
                ->orderBy('usage_type')
                ->get();
        }

        $summaryRows = [];
        $summaryTotal = 0.0;

        if ($this->filterPeriode !== '' && $this->filterUsageType !== '') {
            $detail = (new PdNetflowDetailService)->calculate(
                $this->filterPeriode,
                $this->filterUsageType,
                null,
                null,
            );
            $pdRates = PdNetflowResult::query()
                ->where('calculation_period', $this->filterPeriode)
                ->where('usage_type', (int) $this->filterUsageType)
                ->get()
                ->keyBy('from_bucket_id');
            $lgdRate = (float) (LgdExpectedRecoveriesResult::query()
                ->where('calculation_period', $this->filterPeriode)
                ->where('usage_type', (int) $this->filterUsageType)
                ->where('is_all_account', true)
                ->latest('id')
                ->value('lgd_rate') ?? 0);

            foreach ($detail['buckets'] as $bucket) {
                $ead = (float) ($detail['outstanding'][$bucket['id']][$this->filterPeriode] ?? 0);
                $pd = (float) ($pdRates[$bucket['id']]->pd_rate ?? 0);
                $impairment = $ead * $pd * $lgdRate;
                $summaryRows[] = [
                    'bucket' => $bucket['code'],
                    'label' => $bucket['label'],
                    'ead' => $ead,
                    'pd' => $pd,
                    'lgd' => $lgdRate,
                    'impairment' => $impairment,
                ];
                $summaryTotal += $impairment;
            }
        }

        // Query 4 tabel tersegmentasi untuk periode yang dipilih
        $segmentedTables = [];
        if ($this->showResults && $this->filterPeriode !== '') {
            $bucketCols = ['from_bucket_id', 'pd_rate', 'transition_rate', 'compound_rate', 'source_outstanding', 'destination_outstanding'];
            $segmentedTables = [
                'Konsolidasi' => PdNetflowConsolidated::select($bucketCols)->where('calculation_period', $this->filterPeriode)->with('fromBucket')->orderBy('from_bucket_id')->get(),
                'Modal Kerja' => PdNetflowModalKerja::select($bucketCols)->where('calculation_period', $this->filterPeriode)->with('fromBucket')->orderBy('from_bucket_id')->get(),
                'Investasi' => PdNetflowInvestasi::select($bucketCols)->where('calculation_period', $this->filterPeriode)->with('fromBucket')->orderBy('from_bucket_id')->get(),
                'Konsumsi' => PdNetflowKonsumsi::select($bucketCols)->where('calculation_period', $this->filterPeriode)->with('fromBucket')->orderBy('from_bucket_id')->get(),
            ];
        }

        return view('livewire.pd-netflow.pd-netflow-result-index', [
            'results' => $results,
            'periods' => $periods,
            'usageTypes' => UsageType::cases(),
            'runLogs' => $runLogs,
            'summaryRows' => $summaryRows,
            'summaryTotal' => $summaryTotal,
            'segmentedTables' => $segmentedTables,
        ]);
    }
}
