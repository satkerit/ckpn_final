<?php

declare(strict_types=1);

namespace App\Livewire\Ckpn;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\CkpnCollectiveCalculationJob;
use App\Models\CalculationParameter;
use App\Models\CalculationRunLog;
use App\Models\CkpnCollectiveResult;
use App\Models\CkpnPeriod;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\PdMigrationResult;
use App\Models\PdNetflowResult;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman tabel hasil CKPN Kolektif + trigger perhitungan + hapus per periode.
 * State machine tombol mengikuti pola PD Netflow (Hitung → Tampilkan → Rekalkulasi/Hapus).
 * Guard: PD dan LGD untuk periode harus tersedia sebelum perhitungan dijalankan.
 * Metode PD bersifat dinamis per segmen (netflow|migration) dari parameter.
 * Ref: PRD Bab 11
 */
#[Layout('layouts.app', ['title' => 'Hasil CKPN Kolektif'])]
class CkpnCollectiveResultIndex extends Component
{
    use WithPagination;

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url(as: 'search')]
    public string $search = '';

    public string $runPeriode = '';

    public bool $isRunning = false;

    /** Aksi yang menunggu konfirmasi: 'hitung' | 'rekalkulasi' | 'hapus' | '' */
    public string $confirmingAction = '';

    /**
     * Metode PD yang dipilih user saat mode dual aktif sebelum menjalankan perhitungan.
     * Nilai: 'netflow' | 'migration' | '' (kosong = belum dipilih)
     */
    public string $selectedPdMethodForCalc = '';

    /** true jika periode $runPeriode sudah pernah dihitung (ada di ckpn_collective_result) */
    public bool $runPeriodeHasResult = false;

    /** true setelah user klik Tampilkan Data atau setelah perhitungan selesai */
    public bool $showResults = false;

    /** Bulk select */
    public array $selectedIds = [];

    public bool $selectAll = false;

    public ?int $deletingId = null;

    public string $flashMessage = '';

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->runPeriode = (string) (CkpnPeriod::query()
            ->latest('period')
            ->value('period') ?? '');

        $this->runPeriodeHasResult = $this->runPeriode !== ''
            && CkpnCollectiveResult::where('calculation_period', $this->runPeriode)->exists();
    }

    public function updatingSearch(): void
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
        $this->confirmingAction = '';
        $this->showResults = false;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->runPeriodeHasResult = $this->runPeriode !== ''
            && CkpnCollectiveResult::where('calculation_period', $this->runPeriode)->exists();
    }

    /**
     * Set filter periode ke runPeriode agar tabel hasil langsung menampilkan data
     * periode yang dipilih — tanpa redirect/reload halaman.
     */
    public function tampilkanData(): void
    {
        $this->filterPeriode = $this->runPeriode;
        $this->showResults = true;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedIds = CkpnCollectiveResult::where('calculation_period', $this->filterPeriode)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        } else {
            $this->selectedIds = [];
        }
    }

    public function konfirmasiBulkHapus(): void
    {
        if (empty($this->selectedIds)) {
            $this->dispatch('notify', type: 'error', message: 'Pilih minimal satu baris untuk dihapus.');

            return;
        }

        $this->confirmingAction = 'bulk_hapus';
    }

    public function bulkHapus(): void
    {
        $this->confirmingAction = '';

        $deleted = CkpnCollectiveResult::whereIn('id', $this->selectedIds)->delete();

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->flashMessage = "{$deleted} baris berhasil dihapus.";
        $this->flashType = 'success';
        $this->dispatch('notify', type: 'success', message: $this->flashMessage);
    }

    public function batalBulkHapus(): void
    {
        $this->confirmingAction = '';
    }

    /** Tampilkan dialog konfirmasi jalankan perhitungan. */
    /**
     * True jika parameter allow_dual_pd_method=1.
     * Mode dual: user memilih metode PD saat konfirmasi perhitungan CKPN Kolektif.
     * Ref: PRD Bab 12
     */
    public function isDualPdMethod(): bool
    {
        return CalculationParameter::getValue('allow_dual_pd_method', '0') === '1';
    }

    public function confirmJalankan(): void
    {
        $periode = trim($this->runPeriode);

        if (! $this->isValidPeriod($periode)) {
            return;
        }

        // Anti double proses: periode sudah pernah dihitung → arahkan ke Rekalkulasi
        $doneCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->whereIn('status', [RunStatus::Completed->value, RunStatus::Approved->value])
            ->count();

        if ($doneCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah pernah dihitung. Gunakan tombol Rekalkulasi jika ingin menghitung ulang.");

            return;
        }

        // Mode single: validasi pd_method periode harus cocok dengan parameter sistem. Ref: PRD Bab 12
        if (! $this->isDualPdMethod()) {
            if (! $this->validatePdMethodConsistency($periode)) {
                return;
            }
        }

        if (! $this->pdLgdAvailable($periode)) {
            return;
        }

        // Mode dual: tampilkan pilihan metode PD sebelum konfirmasi akhir
        if ($this->isDualPdMethod()) {
            $this->selectedPdMethodForCalc = '';
            $this->confirmingAction = 'hitung_dual';

            return;
        }

        $this->confirmingAction = 'hitung';
    }

    /** Tampilkan dialog konfirmasi rekalkulasi. */
    public function confirmRekalkulasi(): void
    {
        $periode = trim($this->runPeriode);

        if (! $this->isValidPeriod($periode)) {
            return;
        }

        if ($this->approvedRunCount($periode) > 0) {
            $this->dispatch('notify', type: 'error', message: "Periode {$periode} sudah berstatus Approved dan tidak dapat direkalkulasi.");

            return;
        }

        // Mode single: validasi pd_method periode harus cocok dengan parameter sistem. Ref: PRD Bab 12
        if (! $this->isDualPdMethod()) {
            if (! $this->validatePdMethodConsistency($periode)) {
                return;
            }
        }

        if (! $this->pdLgdAvailable($periode)) {
            return;
        }

        // Mode dual: tampilkan pilihan metode PD sebelum konfirmasi akhir
        if ($this->isDualPdMethod()) {
            $this->selectedPdMethodForCalc = '';
            $this->confirmingAction = 'rekalkulasi_dual';

            return;
        }

        $this->confirmingAction = 'rekalkulasi';
    }

    /**
     * Jalankan perhitungan dengan metode PD yang dipilih user (mode dual).
     * Dipanggil dari blade saat confirmingAction = 'hitung_dual' atau 'rekalkulasi_dual'.
     * Ref: PRD Bab 12
     */
    public function jalankanDenganMetode(string $method): void
    {
        if (! in_array($method, ['netflow', 'migration'], true)) {
            $this->dispatch('notify', type: 'error', message: 'Metode PD tidak valid.');

            return;
        }

        $this->selectedPdMethodForCalc = $method;
        $this->confirmingAction = '';

        $this->dispatchCollectiveJobs(trim($this->runPeriode), $method);
    }

    /** Batalkan konfirmasi mode dual. */
    public function batalDual(): void
    {
        $this->confirmingAction = '';
        $this->selectedPdMethodForCalc = '';
    }

    /** Tampilkan dialog konfirmasi hapus seluruh perhitungan periode. */
    public function confirmHapus(): void
    {
        $periode = trim($this->runPeriode);

        if (! $this->isValidPeriod($periode)) {
            return;
        }

        if ($this->approvedRunCount($periode) > 0) {
            $this->dispatch('notify', type: 'error', message: "Periode {$periode} sudah berstatus Approved dan tidak dapat dihapus.");

            return;
        }

        $this->confirmingAction = 'hapus';
    }

    /**
     * Dispatch CkpnCollectiveCalculationJob per UsageType.
     * Metode PD dinamis per segmen dari parameter. Idempotent. Ref: AGENTS.md §4
     */
    public function jalankanPerhitungan(): void
    {
        $this->confirmingAction = '';
        $periode = trim($this->runPeriode);

        // Guard defensif kedua: tolak jika periode sudah pernah dihitung (anti double proses)
        $doneCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->whereIn('status', [RunStatus::Completed->value, RunStatus::Approved->value])
            ->count();

        if ($doneCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Periode {$periode} sudah pernah dihitung. Gunakan tombol Rekalkulasi.");

            return;
        }

        $dispatched = 0;

        // pluck('usage_type') mengembalikan instance UsageType karena cast enum di model — bandingkan enum vs enum.
        $runningUsageTypes = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->pluck('usage_type')
            ->all();

        foreach (UsageType::cases() as $usageType) {
            if (in_array($usageType, $runningUsageTypes, true)) {
                continue;
            }

            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::CkpnCollective,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => auth()->id(),
            ]);

            // Pass pdMethod eksplisit agar job tidak perlu query ulang — Ref: PRD Bab 12.3
            CkpnCollectiveCalculationJob::dispatch(
                $runLog->id,
                $usageType->value,
                $periode,
                $this->resolvePdMethod($usageType),
            );
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->dispatch('notify', type: 'warning', message: 'Perhitungan untuk periode '.$periode.' sudah berjalan atau sedang diproses.');

            return;
        }

        $this->dispatch('notify', type: 'success', message: $dispatched.' job CKPN Kolektif berhasil diantrikan untuk periode '.$periode.'.');
        $this->isRunning = true;
        $this->showResults = true;
    }

    /**
     * Rekalkulasi: buat run log baru per UsageType lalu dispatch ulang job.
     * Run log lama tidak dihapus — history tetap terjaga. Ref: PRD Bab 13.2
     */
    public function rekalkulasi(): void
    {
        $this->confirmingAction = '';
        $periode = trim($this->runPeriode);

        if ($this->approvedRunCount($periode) > 0) {
            $this->dispatch('notify', type: 'error', message: "Periode {$periode} sudah berstatus Approved dan tidak dapat direkalkulasi.");

            return;
        }

        $runningCount = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        if ($runningCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Perhitungan periode {$periode} masih berjalan. Tunggu selesai sebelum rekalkulasi.");

            return;
        }

        foreach (UsageType::cases() as $usageType) {
            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::CkpnCollective,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => auth()->id(),
            ]);

            CkpnCollectiveCalculationJob::dispatch(
                $runLog->id,
                $usageType->value,
                $periode,
                $this->resolvePdMethod($usageType),
            );
        }

        $this->dispatch('notify', type: 'success', message: 'Rekalkulasi CKPN Kolektif periode '.$periode.' berhasil diantrikan.');
        $this->isRunning = true;
        $this->showResults = true;
    }

    /**
     * Hapus seluruh hasil perhitungan periode terpilih beserta run log.
     * Ref: PRD Bab 13.2 (immutability snapshot — proteksi status Approved)
     */
    public function hapusPerhitungan(): void
    {
        $this->confirmingAction = '';
        $periode = trim($this->runPeriode);

        if ($this->approvedRunCount($periode) > 0) {
            $this->dispatch('notify', type: 'error', message: "Periode {$periode} sudah berstatus Approved dan tidak dapat dihapus.");

            return;
        }

        $runLogIds = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->pluck('id')
            ->all();

        if ($runLogIds === []) {
            $this->dispatch('notify', type: 'warning', message: "Tidak ada data perhitungan untuk periode {$periode}.");

            return;
        }

        $periode = trim($this->runPeriode);

        DB::transaction(function () use ($runLogIds, $periode): void {
            // Hapus via run_log_id jika ada, lalu fallback hapus semua baris periode agar tidak ada orphan
            if ($runLogIds !== []) {
                DB::table('ckpn_collective_result')
                    ->whereIn('calculation_run_log_id', $runLogIds)
                    ->delete();

                CalculationRunLog::query()->whereIn('id', $runLogIds)->delete();
            }

            // Bersihkan sisa orphan (baris tanpa run_log atau run_log sudah dihapus)
            DB::table('ckpn_collective_result')
                ->where('calculation_period', $periode)
                ->delete();
        });

        $this->redirect(route('kalkulasi.ckpn.index'), navigate: true);
    }

    /**
     * Poll status job per UsageType. Setelah semua job selesai, tampilkan hasil otomatis.
     */
    public function pollJobStatus(): void
    {
        if (! $this->isRunning || $this->runPeriode === '') {
            return;
        }

        $activeCount = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        $wasRunning = $this->isRunning;
        $this->isRunning = $activeCount > 0;

        if ($wasRunning && ! $this->isRunning) {
            $this->runPeriodeHasResult = CkpnCollectiveResult::where('calculation_period', $this->runPeriode)->exists();
            $this->filterPeriode = $this->runPeriode;
            $this->showResults = true;
        }
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->deletingId = $id;
    }

    public function batalHapus(): void
    {
        $this->deletingId = null;
    }

    public function hapus(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        try {
            CkpnCollectiveResult::findOrFail($this->deletingId)->delete();
            $this->flashMessage = 'Baris berhasil dihapus.';
            $this->flashType = 'success';
        } catch (\RuntimeException $e) {
            $this->flashMessage = $e->getMessage();
            $this->flashType = 'error';
        }

        $this->deletingId = null;
        $this->resetPage();
        $this->dispatch('$refresh');
    }

    /**
     * Validasi konsistensi metode PD: pd_method periode harus cocok dengan yang tersedia.
     * Mode single: jika periode pakai netflow, hanya boleh hitung netflow, dan sebaliknya.
     * Ref: PRD Bab 12
     */
    private function validatePdMethodConsistency(string $periode): bool
    {
        $ckpnPeriod = CkpnPeriod::where('period', $periode)->first();

        if (! $ckpnPeriod) {
            return true; // periode tidak terdaftar, biarkan guard lain yang menangkap
        }

        $pdMethodPeriode = $ckpnPeriod->pd_method; // 'netflow' | 'migration'

        // Cek apakah data PD yang tersedia sesuai metode yang ditetapkan di periode
        foreach (UsageType::cases() as $usageType) {
            if ($pdMethodPeriode === 'netflow') {
                $hasPd = PdNetflowResult::where('usage_type', $usageType->value)
                    ->where('calculation_period', $periode)
                    ->exists();

                if (! $hasPd) {
                    $this->dispatch('notify', type: 'error', message: "Periode {$periode} ditetapkan menggunakan metode PD Netflow, namun data PD Netflow untuk segmen {$usageType->label()} belum tersedia. Jalankan perhitungan PD Netflow terlebih dahulu.");

                    return false;
                }

                // Tolak jika user mencoba jalankan CKPN dengan PD Migration pada periode netflow
                $hasMigration = PdMigrationResult::where('usage_type', $usageType->value)
                    ->where('calculation_period', $periode)
                    ->exists();

                if (! $hasMigration && $this->resolvePdMethod($usageType) === 'migration') {
                    $this->dispatch('notify', type: 'error', message: "Periode {$periode} ditetapkan menggunakan metode PD Netflow. Parameter sistem tidak boleh dikonfigurasi ke PD Migration untuk periode ini.");

                    return false;
                }
            } elseif ($pdMethodPeriode === 'migration') {
                $hasPd = PdMigrationResult::where('usage_type', $usageType->value)
                    ->where('calculation_period', $periode)
                    ->exists();

                if (! $hasPd) {
                    $this->dispatch('notify', type: 'error', message: "Periode {$periode} ditetapkan menggunakan metode PD Migration, namun data PD Migration untuk segmen {$usageType->label()} belum tersedia. Jalankan perhitungan PD Migration terlebih dahulu.");

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Dispatch job CKPN Kolektif per UsageType dengan override metode PD (mode dual).
     * Digunakan saat user memilih metode secara eksplisit. Ref: PRD Bab 12
     *
     * @param  string  $pdMethodOverride  'netflow' | 'migration'
     */
    private function dispatchCollectiveJobs(string $periode, string $pdMethodOverride): void
    {
        $dispatched = 0;

        $runningUsageTypes = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->pluck('usage_type')
            ->all();

        foreach (UsageType::cases() as $usageType) {
            if (in_array($usageType, $runningUsageTypes, true)) {
                continue;
            }

            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::CkpnCollective,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => auth()->id(),
            ]);

            CkpnCollectiveCalculationJob::dispatch(
                $runLog->id,
                $usageType->value,
                $periode,
                $pdMethodOverride,
            );
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->dispatch('notify', type: 'warning', message: "Perhitungan untuk periode {$periode} sudah berjalan atau sedang diproses.");

            return;
        }

        $this->dispatch('notify', type: 'success', message: "{$dispatched} job CKPN Kolektif berhasil diantrikan untuk periode {$periode} menggunakan PD ".strtoupper($pdMethodOverride).'.');
        $this->isRunning = true;
        $this->showResults = true;
    }

    private function isValidPeriod(string $periode): bool
    {
        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid. Gunakan format yyyymm (contoh: 202412).');

            return false;
        }

        return true;
    }

    private function approvedRunCount(string $periode): int
    {
        return CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::CkpnCollective->value)
            ->where('status', RunStatus::Approved->value)
            ->count();
    }

    /**
     * Guard: cek ketersediaan PD & LGD untuk periode.
     * PD method per segmen dibaca dari parameter ckpn_collective_pd_method.
     * Ref: PRD Bab 11, PRD Bab 12.3
     */
    private function pdLgdAvailable(string $periode): bool
    {
        $missing = [];

        foreach (UsageType::cases() as $usageType) {
            $pdMethod = $this->resolvePdMethod($usageType);
            $hasPd = $pdMethod === 'netflow'
                ? PdNetflowResult::where('usage_type', $usageType->value)->where('calculation_period', $periode)->exists()
                : PdMigrationResult::where('usage_type', $usageType->value)->where('calculation_period', $periode)->exists();

            $hasLgd = LgdExpectedRecoveriesResult::where('usage_type', $usageType->value)
                ->where('calculation_period', $periode)
                ->exists();

            if (! $hasPd || ! $hasLgd) {
                $missing[] = sprintf(
                    '%s (PD %s: %s, LGD ER: %s)',
                    $usageType->label(),
                    strtoupper($pdMethod),
                    $hasPd ? 'ada' : 'belum ada',
                    $hasLgd ? 'ada' : 'belum ada',
                );
            }
        }

        if ($missing !== []) {
            $detail = implode('; ', $missing);
            $this->dispatch('notify', type: 'error', message: "CKPN Kolektif tidak dapat dijalankan. Data berikut belum tersedia untuk periode {$periode}: {$detail}");

            return false;
        }

        return true;
    }

    /**
     * Resolve metode PD untuk UsageType dari parameter.
     * Default: 'netflow'. Ref: PRD Bab 12.3
     */
    private function resolvePdMethod(UsageType $usageType): string
    {
        return (string) (CalculationParameter::where('parameter_key', 'ckpn_collective_pd_method')
            ->where(fn ($q) => $q->where('usage_type', $usageType->value)->orWhereNull('usage_type'))
            ->orderByRaw('usage_type IS NULL ASC')
            ->value('parameter_value') ?? 'netflow');
    }

    public function render(): View
    {
        $baseQuery = CkpnCollectiveResult::query()
            ->with(['financingAccount', 'pdBucket'])
            ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
            ->when($this->filterPeriode !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriode))
            ->when($this->search, fn ($q) => $q->where(
                fn ($w) => $w
                    ->where('calculation_period', 'like', "%{$this->search}%")
                    ->orWhere('usage_type', 'like', "%{$this->search}%")
                    ->orWhereHas('financingAccount', fn ($fa) => $fa->where('customer_name', 'like', "%{$this->search}%"))
            ))
            ->orderByDesc('calculation_period')
            ->orderBy('usage_type');

        $results = $this->showResults
            ? (clone $baseQuery)->paginate(25)
            : CkpnCollectiveResult::query()->whereRaw('0=1')->paginate(25);

        $totalCkpn = $this->showResults
            ? (float) (clone $baseQuery)->sum('ckpn_amount')
            : 0.0;

        $totalOutstanding = $this->showResults
            ? (float) (clone $baseQuery)->sum('ead')
            : 0.0;

        $runLogs = $this->runPeriode !== ''
            ? CalculationRunLog::where('period', $this->runPeriode)
                ->where('run_type', RunType::CkpnCollective->value)
                ->orderBy('usage_type')
                ->get()
            : collect();

        // Info metode PD per segmen untuk ditampilkan di panel (dinamis)
        $pdMethodPerSegmen = collect(UsageType::cases())->mapWithKeys(
            fn ($ut) => [$ut->label() => strtoupper($this->resolvePdMethod($ut))]
        );

        // Availability guard untuk periode yang dipilih di runPeriode
        $pdLgdAvailable = true;
        $pdLgdMissing = [];
        if ($this->runPeriode !== '') {
            foreach (UsageType::cases() as $usageType) {
                $pdMethod = $this->resolvePdMethod($usageType);
                $hasPd = $pdMethod === 'netflow'
                    ? PdNetflowResult::where('usage_type', $usageType->value)->where('calculation_period', $this->runPeriode)->exists()
                    : PdMigrationResult::where('usage_type', $usageType->value)->where('calculation_period', $this->runPeriode)->exists();
                $hasLgd = LgdExpectedRecoveriesResult::where('usage_type', $usageType->value)->where('calculation_period', $this->runPeriode)->exists();

                if (! $hasPd || ! $hasLgd) {
                    $pdLgdAvailable = false;
                    $pdLgdMissing[] = $usageType->label().' (PD: '.($hasPd ? 'ada' : 'belum').', LGD: '.($hasLgd ? 'ada' : 'belum').')';
                }
            }
        }

        $periods = CkpnPeriod::query()->orderByDesc('period')->get(['period', 'status']);

        $periodCalculated = $this->runPeriode !== ''
            && CkpnCollectiveResult::where('calculation_period', $this->runPeriode)->exists();

        // Ringkasan per segmen sesuai kertas kerja B: Segmen | EAD | Avg PD | Avg LGD | Total CKPN
        $summaryPerSegment = [];
        if ($this->showResults && $this->filterPeriode !== '') {
            $summaryRows = DB::table('ckpn_collective_result')
                ->where('calculation_period', $this->filterPeriode)
                ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
                ->select(
                    'usage_type',
                    DB::raw('SUM(ead) as total_ead'),
                    DB::raw('SUM(ckpn_amount) as total_ckpn'),
                    DB::raw('SUM(pd_rate * ead) / NULLIF(SUM(ead), 0) as avg_pd_rate'),
                    DB::raw('SUM(lgd_rate * ead) / NULLIF(SUM(ead), 0) as avg_lgd_rate'),
                    DB::raw('pd_method_used'),
                    DB::raw('lgd_method_used'),
                    DB::raw('COUNT(*) as account_count')
                )
                ->groupBy('usage_type', 'pd_method_used', 'lgd_method_used')
                ->orderBy('usage_type')
                ->get();

            foreach ($summaryRows as $row) {
                $usageTypeEnum = UsageType::tryFrom((int) $row->usage_type);
                $label = $usageTypeEnum ? $usageTypeEnum->label() : (string) $row->usage_type;
                $summaryPerSegment[] = [
                    'label' => $label,
                    'total_ead' => (float) $row->total_ead,
                    'total_ckpn' => (float) $row->total_ckpn,
                    'avg_pd_rate' => (float) $row->avg_pd_rate,
                    'avg_lgd_rate' => (float) $row->avg_lgd_rate,
                    'pd_method' => strtoupper((string) $row->pd_method_used),
                    'lgd_method' => strtoupper((string) $row->lgd_method_used),
                    'account_count' => (int) $row->account_count,
                ];
            }
        }

        return view('livewire.ckpn.ckpn-collective-result-index', [
            'results' => $results,
            'usageTypes' => UsageType::cases(),
            'runLogs' => $runLogs,
            'periods' => $periods,
            'totalCkpn' => $totalCkpn,
            'totalOutstanding' => $totalOutstanding,
            'pdMethodPerSegmen' => $pdMethodPerSegmen,
            'pdLgdAvailable' => $pdLgdAvailable,
            'pdLgdMissing' => $pdLgdMissing,
            'periodCalculated' => $periodCalculated,
            'summaryPerSegment' => $summaryPerSegment,
        ]);
    }
}
