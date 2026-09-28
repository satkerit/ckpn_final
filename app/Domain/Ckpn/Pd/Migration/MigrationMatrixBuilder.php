<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Migration;

use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Enums\UsageType;
use App\Models\FinancingAccountPeriod;
use App\Models\FinancingOutstandingQuarterly;
use App\Models\QualityGrade;
use Illuminate\Support\Facades\DB;

/**
 * Builds migration matrix for a single cohort.
 * Ref: PRD Bab 8.3 Langkah 2-3
 *
 * A cohort = one quarterly position (e.g. 202203 = March 2022).
 * Traces accounts from cohort_period to cohort_period+12months.
 */
final class MigrationMatrixBuilder
{
    /**
     * Bangun baris matriks migrasi detail (dengan outstanding) untuk satu cohort triwulanan.
     * Ref: PRD Bab 8.3 Langkah 2–3
     *
     * Setiap baris merepresentasikan: "Dari kualitas X, berapa outstanding yang berakhir
     * di kualitas Y (atau Write-Off) dalam 1 tahun?" — lengkap dengan source & destination
     * outstanding agar bisa dirata-berbobot (weighted average) lintas cohort.
     *
     * Struktur baris:
     *   - from_quality_grade_id : int (kualitas awal)
     *   - to_quality_grade_id   : int|null (null = Write-Off / absorbing state)
     *   - migration_rate        : float (0.0–1.0)
     *   - source_outstanding    : float (outstanding awal kualitas asal)
     *   - destination_outstanding: float (outstanding akhir di kualitas tujuan; 0 untuk WO)
     *
     * Sumber data:
     *   - `financing_outstanding_quarterly` untuk outstanding per kualitas per periode
     *   - `financing_account_periods` (writeoff_date terisi) untuk estimasi WO selama cohort
     *   - Filter akad eligible dari parameter `pd_rate_akad_codes` (kosong = semua akad)
     *
     * Catatan WO distribution:
     *   - WO didistribusikan proporsional terhadap outstanding per kualitas (proxy)
     *   - TODO(PRD Bab 12): distribusi WO per kualitas secara account-level tracing belum final
     *
     * @param  string  $cohortPeriod  Periode triwulanan awal (yyyymm, harus Mar/Jun/Sep/Des)
     * @param  string  $endPeriod  Periode triwulanan akhir (= cohortPeriod + 12 bulan)
     * @param  string|null  $officeCode  Kode kantor (level 1 segmentasi); NULL = konsolidasi
     * @return array<int, array{from_quality_grade_id:int, to_quality_grade_id:?int, migration_rate:float, source_outstanding:float, destination_outstanding:float}>
     */
    public function buildRows(UsageType $usageType, string $cohortPeriod, string $endPeriod, ?string $officeCode = null): array
    {
        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $usageType->value);

        if ($officeCode !== null) {
            // Jalur per-kantor: tabel financing_outstanding_quarterly tidak punya dimensi kantor,
            // jadi outstanding per grade dihitung langsung dari financing_account_periods.
            // Ref: AGENTS.md §4 — query builder untuk agregasi berat
            $sourceOs = $this->gradeOutstandingFromPeriods($usageType, $cohortPeriod, $akadCodes, $officeCode);
            $destOs = $this->gradeOutstandingFromPeriods($usageType, $endPeriod, $akadCodes, $officeCode);
            $woAmount = $this->writeoffAmount($usageType, $cohortPeriod, $endPeriod, $akadCodes, $officeCode);
        } else {
            // Load outstanding at cohort start (source)
            $sourceData = FinancingOutstandingQuarterly::where('usage_type', $usageType->value)
                ->where('period', $cohortPeriod)
                ->get()
                ->keyBy('quality_grade_id');

            // Load outstanding at cohort end (destination)
            $destData = FinancingOutstandingQuarterly::where('usage_type', $usageType->value)
                ->where('period', $endPeriod)
                ->get()
                ->keyBy('quality_grade_id');

            $sourceOs = [];
            foreach ($sourceData as $gradeId => $row) {
                $sourceOs[$gradeId] = (float) $row->total_outstanding;
            }

            $destOs = [];
            foreach ($destData as $gradeId => $row) {
                $destOs[$gradeId] = (float) $row->total_outstanding;
            }

            $woAmount = $this->writeoffAmount($usageType, $cohortPeriod, $endPeriod, $akadCodes, null);
        }

        $allGrades = QualityGrade::orderBy('collectibility_number')->get();
        $totalSource = array_sum($sourceOs);

        $rows = [];

        foreach ($allGrades as $fromGrade) {
            $sourceOutstanding = $sourceOs[$fromGrade->id] ?? 0.0;

            if ($sourceOutstanding <= 0) {
                continue;
            }

            // Migration to each quality grade
            foreach ($allGrades as $toGrade) {
                $destOutstanding = $destOs[$toGrade->id] ?? 0.0;
                $rate = $destOutstanding / $sourceOutstanding;
                $rows[] = [
                    'from_quality_grade_id' => $fromGrade->id,
                    'to_quality_grade_id' => $toGrade->id,
                    'migration_rate' => min($rate, 1.0),
                    'source_outstanding' => $sourceOutstanding,
                    'destination_outstanding' => $destOutstanding,
                ];
            }

            // Migration to WO (absorbing state) — distribusi proporsional (proxy)
            // TODO(PRD Bab 12): WO distribution by grade requires account-level tracing
            $woRate = $totalSource > 0
                ? (float) $woAmount * ($sourceOutstanding / $totalSource) / $sourceOutstanding
                : 0.0;
            $rows[] = [
                'from_quality_grade_id' => $fromGrade->id,
                'to_quality_grade_id' => null,
                'migration_rate' => min($woRate, 1.0),
                'source_outstanding' => $sourceOutstanding,
                'destination_outstanding' => 0.0,
            ];
        }

        return $rows;
    }

    /**
     * Bangun matriks migrasi (migration rates) untuk satu cohort triwulanan.
     * Ref: PRD Bab 8.3 Langkah 2–3
     *
     * Konsep cohort:
     *   Cohort = posisi triwulanan awal (mis. 202203 = Maret 2022).
     *   Sistem melacak pergerakan outstanding dari cohort_period ke cohort_period + 12 bulan.
     *   Setiap baris matriks menggambarkan: "Dari kualitas X, berapa proporsi yang berakhir di kualitas Y
     *   atau menjadi Write-Off dalam 1 tahun?"
     *
     * Cara baca hasil (return value):
     *   - Outer key = from_quality_grade_id (ID kualitas awal, dari tabel quality_grades)
     *   - Inner key = to_quality_grade_id (int) → migrasi ke kualitas tsb
     *              = 'wo' (string) → menjadi Write-Off selama periode cohort
     *   - Value = migration_rate (0.0–1.0); contoh: 0.15 = 15% outstanding pindah ke kualitas itu
     *
     * Method ini memproyeksikan {@see buildRows()} ke bentuk matriks ringkas (tanpa outstanding),
     * sehingga kontrak `calculate()` tetap utuh (tidak ada duplikasi logika).
     *
     * @param  string  $cohortPeriod  Periode triwulanan awal (yyyymm, harus Mar/Jun/Sep/Des)
     * @param  string  $endPeriod  Periode triwulanan akhir (= cohortPeriod + 12 bulan)
     * @return array<int, array<int|string, float>>
     */
    public function build(UsageType $usageType, string $cohortPeriod, string $endPeriod, ?string $officeCode = null): array
    {
        $matrix = [];

        foreach ($this->buildRows($usageType, $cohortPeriod, $endPeriod, $officeCode) as $row) {
            $matrix[$row['from_quality_grade_id']][$row['to_quality_grade_id'] ?? 'wo'] = $row['migration_rate'];
        }

        return $matrix;
    }

    /**
     * Outstanding per quality grade dari financing_account_periods untuk pecahan per kantor.
     * Kolektibilitas akun dipetakan ke quality_grades.collectibility_number.
     *
     * @param  string[]|null  $akadCodes
     * @return array<int, float> key = quality_grade_id, value = total outstanding
     */
    private function gradeOutstandingFromPeriods(UsageType $usageType, string $period, ?array $akadCodes, string $officeCode): array
    {
        $rows = AkadEligibilityService::restrict(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->join('quality_grades as qg', 'qg.collectibility_number', '=', 'fap.collectibility')
                ->where('fap.period', $period)
                ->where('fa.usage_type', $usageType->value)
                ->where('fa.office_code', $officeCode)
                // Akad 03 hanya jika sudah JTP pada periode — konsisten dengan PD Netflow
                ->where(function ($q): void {
                    $q->where('fa.akad_code', '!=', '03')
                        ->orWhere(function ($m): void {
                            $m->where('fa.akad_code', '03')
                                ->whereNotNull('fap.maturity_date')
                                ->whereRaw("fap.maturity_date <= LAST_DAY(STR_TO_DATE(CONCAT(fap.period, '01'), '%Y%m%d'))");
                        });
                })
                ->selectRaw('qg.id as quality_grade_id, SUM(fap.outstanding_balance) as total'),
            $akadCodes,
            'fa.akad_code',
        )
            ->groupBy('qg.id')
            ->pluck('total', 'quality_grade_id');

        $map = [];
        foreach ($rows as $gradeId => $total) {
            $map[(int) $gradeId] = (float) $total;
        }

        return $map;
    }

    /**
     * Total writeoff selama window cohort, dengan filter kantor opsional (level 1).
     *
     * @param  string[]|null  $akadCodes
     */
    private function writeoffAmount(UsageType $usageType, string $cohortPeriod, string $endPeriod, ?array $akadCodes, ?string $officeCode): float
    {
        return (float) FinancingAccountPeriod::where('period', '>=', $cohortPeriod)
            ->where('period', '<=', $endPeriod)
            ->whereNotNull('writeoff_date')
            // Akad 03 hanya jika sudah JTP saat writeoff — perlakuan seragam dgn PD Netflow & LGD-ER
            ->whereNot(function ($q): void {
                $q->whereHas('financingAccount', fn ($fa) => $fa->where('akad_code', '03'))
                    ->where(function ($m): void {
                        $m->whereNull('maturity_date')
                            ->orWhereRaw('maturity_date > writeoff_date');
                    });
            })
            ->whereHas('financingAccount', function ($q) use ($usageType, $akadCodes, $officeCode) {
                $q->where('usage_type', $usageType->value)
                    ->when($officeCode !== null, fn ($w) => $w->where('office_code', $officeCode))
                    ->when($akadCodes !== null, fn ($w) => $w->whereIn('akad_code', $akadCodes));
            })
            ->sum('outstanding_balance');
    }
}
