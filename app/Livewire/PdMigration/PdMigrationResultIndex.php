<?php

declare(strict_types=1);

namespace App\Livewire\PdMigration;

use App\Enums\PdMethod;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use App\Jobs\PdMigrationCalculationJob;
use App\Models\CalculationParameter;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\PdMigrationMatrix;
use App\Models\PdMigrationResult;
use App\Models\QualityGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman tabel hasil PD Migration + trigger perhitungan per periode.
 * Ref: PRD Bab 8
 */
#[Layout('layouts.app', ['title' => 'Hasil PD Migration'])]
class PdMigrationResultIndex extends Component
{
    use WithPagination;

    #[Url(as: 'usage_type')]
    public string $filterUsageType = '';

    #[Url(as: 'periode')]
    public string $filterPeriode = '';

    #[Url]
    public string $search = '';

    public string $runPeriode = '';

    public bool $isRunning = false;

    /** Aksi yang menunggu konfirmasi: 'hitung' | 'hapus' | '' */
    public string $confirmingAction = '';

    /** true jika periode $runPeriode sudah ada datanya di pd_migration_result */
    public bool $runPeriodeHasResult = false;

    /** true setelah user klik Tampilkan Data atau setelah job selesai */
    public bool $showResults = false;

    public function updatedRunPeriode(): void
    {
        $this->showResults = false;
        $this->runPeriodeHasResult = $this->runPeriode !== ''
            && PdMigrationResult::where('calculation_period', $this->runPeriode)->exists();
    }

    public function tampilkanData(): void
    {
        $this->filterPeriode = $this->runPeriode;
        $this->showResults = true;
        $this->resetPage();
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
     * Dispatch PdMigrationCalculationJob untuk semua UsageType pada periode terpilih.
     * Ref: PRD Bab 8, AGENTS.md §4 (idempotent)
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

    /**
     * Validasi metode PD: mode single → hanya boleh PD Migration jika pd_method periode = migration.
     * Mode dual (allow_dual_pd_method=1) → kedua metode diizinkan.
     * Ref: AGENTS.md §6, PRD Bab 8
     */
    private function isAllowedPdMethod(string $periode): bool
    {
        $isDual = CalculationParameter::getValue('allow_dual_pd_method', '0') === '1';

        if ($isDual) {
            return true;
        }

        $period = CkpnPeriod::where('period', $periode)->first();

        if ($period === null) {
            return true;
        }

        if ($period->pd_method !== null && $period->pd_method !== PdMethod::Migration) {
            $label = $period->pd_method->label();
            $this->dispatch('notify', type: 'error', message: "Periode {$periode} ditetapkan dengan metode {$label}. Gunakan halaman PD Netflow untuk menghitung periode ini.");

            return false;
        }

        return true;
    }

    /** Tampilkan dialog konfirmasi sebelum hapus data periode. */
    public function confirmHapus(): void
    {
        $this->confirmingAction = 'hapus';
    }

    /**
     * Hapus semua data pd_migration_result + pd_migration_matrix untuk periode terpilih.
     * Ref: PRD Bab 8, AGENTS.md §4 (snapshot immutability — hanya hapus non-Approved)
     */
    public function hapusPerhitungan(): void
    {
        $this->confirmingAction = '';
        $periode = trim($this->filterPeriode ?: $this->runPeriode);

        if ($periode === '') {
            return;
        }

        DB::transaction(function () use ($periode): void {
            // Hapus matrix cohort yang terikat ke run_log periode ini
            $runLogIds = CalculationRunLog::where('period', $periode)
                ->where('run_type', RunType::PdMigration->value)
                ->pluck('id');

            PdMigrationMatrix::whereIn('calculation_run_log_id', $runLogIds)->delete();

            // Hapus hasil PD Migration
            PdMigrationResult::where('calculation_period', $periode)->delete();

            // Tandai run_log sebagai Failed agar tidak orphan
            CalculationRunLog::whereIn('id', $runLogIds)->update(['status' => RunStatus::Failed->value]);
        });

        $this->runPeriodeHasResult = false;
        $this->showResults = false;

        $this->dispatch('notify', type: 'success', message: "Data PD Migration periode {$periode} berhasil dihapus.");
    }

    public function jalankanPerhitungan(): void
    {
        $this->confirmingAction = '';
        $periode = trim($this->runPeriode);

        if ($periode === '' || ! preg_match('/^\d{6}$/', $periode)) {
            $this->dispatch('notify', type: 'error', message: 'Format periode tidak valid. Gunakan format yyyymm (contoh: 202412).');

            return;
        }

        $userId = auth()->id();
        $dispatched = 0;

        // 1 query untuk semua UsageType yang sedang pending/processing (hindari N+1)
        $runningUsageTypes = CalculationRunLog::where('period', $periode)
            ->where('run_type', RunType::PdMigration->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->pluck('usage_type')
            ->all();

        foreach (UsageType::cases() as $usageType) {
            if (in_array($usageType->value, $runningUsageTypes, true)) {
                continue;
            }

            $runLog = CalculationRunLog::create([
                'period' => $periode,
                'run_type' => RunType::PdMigration,
                'usage_type' => $usageType,
                'status' => RunStatus::Pending,
                'triggered_by_user_id' => $userId,
            ]);

            PdMigrationCalculationJob::dispatch($runLog->id, $usageType->value, $periode);
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->dispatch('notify', type: 'warning', message: "Perhitungan untuk periode {$periode} sudah berjalan atau sedang diproses.");
        } else {
            $this->dispatch('notify', type: 'success', message: "Dispatched {$dispatched} job perhitungan PD Migration untuk periode {$periode}.");
        }

        $this->isRunning = true;
    }

    /** Polling status job aktif untuk periode runPeriode. */
    public function pollJobStatus(): void
    {
        if ($this->runPeriode === '') {
            return;
        }

        $activeCount = CalculationRunLog::where('period', $this->runPeriode)
            ->where('run_type', RunType::PdMigration->value)
            ->whereIn('status', [RunStatus::Pending->value, RunStatus::Processing->value])
            ->count();

        $wasRunning = $this->isRunning;
        $this->isRunning = $activeCount > 0;

        // Saat job baru selesai, refresh status hasil dan tampilkan data otomatis
        if ($wasRunning && ! $this->isRunning) {
            $this->runPeriodeHasResult = PdMigrationResult::where('calculation_period', $this->runPeriode)->exists();
            if ($this->runPeriodeHasResult) {
                $this->filterPeriode = $this->runPeriode;
                $this->showResults = true;
            }
        }
    }

    public function render(): View
    {
        $results = PdMigrationResult::query()
            ->with(['calculationRunLog', 'fromQualityGrade'])
            ->when($this->filterUsageType !== '', fn ($q) => $q->where('usage_type', (int) $this->filterUsageType))
            ->when($this->filterPeriode !== '', fn ($q) => $q->where('calculation_period', $this->filterPeriode))
            ->when($this->search, fn ($q) => $q->where(
                fn ($w) => $w
                    ->where('calculation_period', 'like', "%{$this->search}%")
                    ->orWhere('usage_type', 'like', "%{$this->search}%")
            ))
            ->orderByDesc('calculation_period')
            ->orderBy('usage_type')
            ->paginate(25);

        $periods = CkpnPeriod::orderByDesc('period')->pluck('period');

        $runLogs = [];
        if ($this->runPeriode !== '') {
            $runLogs = CalculationRunLog::where('period', $this->runPeriode)
                ->where('run_type', RunType::PdMigration->value)
                ->orderBy('usage_type')
                ->get();
        }

        // ── Migration Matrix per-segment (kertas kerja B2): from_grade × to_grade, di-breakdown per UsageType ──
        // Rate dirata-berbobot (weighted by source_outstanding) per segmen agar mewakili matriks 1-tahun.
        $grades = QualityGrade::orderBy('collectibility_number')->get();
        $matrixBySegment = [];   // [usageTypeValue] => ['matrix'=>[fromCode][toKey]=rate, 'cols'=>[toKey=>label], 'cohortCount'=>int]

        if ($this->showResults && $this->filterPeriode !== '') {
            $matrixRows = PdMigrationMatrix::query()
                ->join('calculation_run_log as crl', 'crl.id', '=', 'pd_migration_matrix.calculation_run_log_id')
                ->where('crl.period', $this->filterPeriode)
                ->when($this->filterUsageType !== '', fn ($q) => $q->where('pd_migration_matrix.usage_type', (int) $this->filterUsageType))
                ->select(
                    'pd_migration_matrix.usage_type',
                    'pd_migration_matrix.from_quality_grade_id',
                    'pd_migration_matrix.to_quality_grade_id',
                    DB::raw('SUM(pd_migration_matrix.migration_rate * COALESCE(pd_migration_matrix.source_outstanding, 1)) as w_rate'),
                    DB::raw('SUM(COALESCE(pd_migration_matrix.source_outstanding, 1)) as w_total'),
                    DB::raw('COUNT(DISTINCT pd_migration_matrix.cohort_period) as cohorts')
                )
                ->groupBy('pd_migration_matrix.usage_type', 'pd_migration_matrix.from_quality_grade_id', 'pd_migration_matrix.to_quality_grade_id')
                ->get();

            foreach ($matrixRows as $row) {
                // usage_type cast ke enum UsageType -> ambil ->value (int)
                $ut = (int) $row->usage_type->value;
                $toKey = $row->to_quality_grade_id ? $row->to_quality_grade_id : 'WO';
                $rate = $row->w_total > 0 ? (float) $row->w_rate / (float) $row->w_total : 0.0;

                $matrixBySegment[$ut]['matrix'][$row->from_quality_grade_id][$toKey] = $rate;
                $matrixBySegment[$ut]['cols'][$toKey] = $row->to_quality_grade_id
                    ? (QualityGrade::find($row->to_quality_grade_id)?->label ?? $toKey)
                    : 'Hapus Buku';
                $matrixBySegment[$ut]['cohortCount'] = max($matrixBySegment[$ut]['cohortCount'] ?? 0, (int) $row->cohorts);
            }

            // Urutkan kolom per segmen: grade naik lalu WO di akhir
            foreach ($matrixBySegment as &$segment) {
                $segment['cols'] = collect($segment['cols'] ?? [])
                    ->sortBy(fn ($label, $code) => $code === 'WO' ? 99 : (int) $code)
                    ->all();
            }
            unset($segment);
        }

        return view('livewire.pd-migration.pd-migration-result-index', [
            'results' => $results,
            'periods' => $periods,
            'usageTypes' => UsageType::cases(),
            'runLogs' => $runLogs,
            'grades' => $grades,
            'matrixBySegment' => $matrixBySegment,
        ]);
    }
}
