<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Lgd\ExpectedRecoveries;

use App\Domain\Ckpn\Lgd\Contracts\LgdCalculationMethodInterface;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Enums\UsageType;
use Illuminate\Support\Facades\DB;

/**
 * Calculates LGD using the Expected Recoveries method.
 * Ref: PRD Bab 9
 *
 * Formula:
 *   Recovery_Rate(year_t) = Total Recoveries(year_t) / Total Writeoff(year_t)
 *   Expected_Recovery_Rate = avg(Recovery_Rate) over window
 *   LGD = 1 - Expected_Recovery_Rate
 *
 * Sumber writeoff: akun dengan writeoff_status NOT NULL pada periode perhitungan,
 * writeoff_date dalam 5 tahun ke belakang, akad 03 hanya jika maturity_date <= writeoff_date.
 * outstanding_writeoff = outstanding_balance pada periode = DATE_FORMAT(writeoff_date, '%Y%m').
 * total_recovery = outstanding_writeoff - outstanding_periode_perhitungan (0 jika akun tidak muncul).
 */
final class LgdExpectedRecoveriesCalculator implements LgdCalculationMethodInterface
{
    public function __construct(
        private readonly int $windowYears = 5,
        private readonly bool $useAllAccount = false,
    ) {}

    /**
     * Hitung LGD ER ringkas (hanya lgd_rate) untuk satu segmen dan periode.
     * Shortcut untuk kebutuhan kalkulasi CKPN Kolektif — gunakan calculateWithDetails()
     * bila membutuhkan breakdown writeoff/recovery per tahun untuk snapshot.
     *
     * @param  string  $calculationPeriod  Format yyyymm, mis. 202412
     * @param  string|null  $officeCode  Kode kantor (level 1 segmentasi); NULL = konsolidasi — Ref: PRD Bab 5
     * @return float LGD rate (0.0–1.0)
     */
    public function calculate(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null, ?string $akadCode = null): float
    {
        return $this->calculateWithDetails($usageType, $calculationPeriod, $officeCode, $akadCode)['lgd_rate'];
    }

    /**
     * Hitung LGD Expected Recoveries lengkap per segmen untuk satu periode.
     * Ref: PRD Bab 9
     *
     * Konsep:
     *   Mengukur berapa proporsi outstanding yang belum tertagih (= LGD) dari total piutang
     *   yang sudah di-writeoff selama 5 tahun terakhir. Semakin tinggi recovery, semakin kecil LGD.
     *
     * Formula:
     *   LGD = (Total OS Writeoff − Total OS Recovery) / Total OS Writeoff
     *   Hasil di-clamp ke rentang [0.0, 1.0].
     *
     * Alur perhitungan (4 langkah):
     *   1. Tentukan window: periode akhir = akhir bulan calculationPeriod,
     *      window mundur $windowYears tahun (default 5 tahun).
     *   2. Kumpulkan writeoff per tahun: akun WO dalam window, outstanding saat tanggal WO.
     *      Akad 03 hanya masuk jika maturity_date <= writeoff_date (sudah JTP).
     *   3. Kumpulkan recovery per tahun: selisih outstanding saat WO vs outstanding periode ini.
     *      Jika akun tidak muncul di periode perhitungan → current outstanding = 0 → recovery = 100%.
     *   4. Fallback: jika data segmen kosong (total writeoff = 0), hitung ulang dengan semua segmen
     *      (is_all_account = true) — Ref: PRD Bab 9.1.
     *
     * Prasyarat:
     *   - Data `financing_account_periods` terisi lengkap untuk seluruh periode dalam window.
     *   - Parameter `lgd_rate_akad_codes` tersedia di `calculation_parameters` (kosong = semua akad).
     *
     * @param  string  $calculationPeriod  Format yyyymm, mis. 202412
     * @param  string|null  $officeCode  Kode kantor (level 1 segmentasi); NULL = konsolidasi — Ref: PRD Bab 5
     * @return array{
     *   lgd_rate: float,
     *   expected_recovery_rate: float,
     *   total_writeoff: float,
     *   total_recovery: float,
     *   is_all_account: bool,
     *   writeoff_by_year: array<string,float>,
     *   recovery_by_year: array<string,float>
     * }
     */
    public function calculateWithDetails(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null, ?string $akadCode = null): array
    {
        // Batas tanggal: akhir bulan periode perhitungan → awal jendela 5 tahun ke belakang
        // Ref: PRD Bab 9.1 — window 5 tahun bergerak mengikuti posisi
        $periodEndDate = $this->periodEndDate($calculationPeriod);
        $windowStartDate = date('Y-m-d', strtotime("-{$this->windowYears} years", strtotime($periodEndDate)));

        // Daftar akad eligible dari parameter (kosong = semua akad) — Ref: parameter lgd_rate_akad_codes
        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $usageType?->value);

        $writeoffByYear = $this->aggregateWriteoffByYear($usageType, $calculationPeriod, $windowStartDate, $akadCodes, $officeCode, $akadCode);
        $recoveryByYear = $this->aggregateRecoveryByYear($usageType, $calculationPeriod, $windowStartDate, $akadCodes, $officeCode, $akadCode);

        $isAllAccount = false;

        // Fallback ke all-account jika data segmen tidak cukup — Ref: PRD Bab 9.1
        if ($this->useAllAccount || array_sum($writeoffByYear) <= 0) {
            $writeoffByYear = $this->aggregateWriteoffByYear(null, $calculationPeriod, $windowStartDate, $akadCodes, $officeCode, $akadCode);
            $recoveryByYear = $this->aggregateRecoveryByYear(null, $calculationPeriod, $windowStartDate, $akadCodes, $officeCode, $akadCode);
            $isAllAccount = true;
        }

        // LGD ER = (Total OS Writeoff - Total OS Recovery) / Total OS Writeoff — Ref: PRD Bab 9
        $totalWriteoff = (float) array_sum($writeoffByYear);
        $totalRecovery = (float) array_sum($recoveryByYear);

        $lgdRate = $totalWriteoff > 0
            ? max(0.0, min(($totalWriteoff - $totalRecovery) / $totalWriteoff, 1.0))
            : 0.0;

        return [
            'lgd_rate' => $lgdRate,
            'expected_recovery_rate' => $lgdRate,
            'total_writeoff' => array_sum($writeoffByYear),
            'total_recovery' => array_sum($recoveryByYear),
            'is_all_account' => $isAllAccount,
            'writeoff_by_year' => $writeoffByYear,
            'recovery_by_year' => $recoveryByYear,
        ];
    }

    /**
     * Aggregate total writeoff per tahun.
     * Langkah:
     *   1. Ambil daftar akun dengan writeoff_status NOT NULL pada periode perhitungan (Poin 1).
     *   2. Filter yang writeoff_date-nya dalam window 5 tahun ke belakang (Poin 2).
     *   3. Join ke baris periode = DATE_FORMAT(writeoff_date, '%Y%m') untuk ambil outstanding saat WO (Poin 3).
     *   4. Akad 03 hanya jika maturity_date <= writeoff_date (sudah JTP saat WO).
     * Uses query builder for performance — Ref: AGENTS.md §4
     *
     * @return array<string, float> Key = tahun (e.g. '2022'), value = total nominal
     */
    private function aggregateWriteoffByYear(?UsageType $usageType, string $calculationPeriod, string $windowStartDate, ?array $akadCodes = null, ?string $officeCode = null, ?string $akadCode = null): array
    {
        // Sub-query: daftar akun WO pada periode perhitungan beserta writeoff_date-nya
        // (writeoff_status NOT NULL = akun telah di-write off per periode tsb)
        $query = AkadEligibilityService::restrict(
            DB::table('financing_account_periods as fap_calc')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap_calc.financing_account_id')
                // join ke baris periode = DATE_FORMAT(writeoff_date, '%Y%m') untuk outstanding saat WO
                ->joinSub(
                    DB::table('financing_account_periods')
                        ->select(['financing_account_id', 'period', 'outstanding_balance', 'writeoff_date', 'maturity_date']),
                    'fap_wo',
                    function ($join): void {
                        $join->on('fap_wo.financing_account_id', '=', 'fap_calc.financing_account_id')
                            ->whereRaw("fap_wo.period = DATE_FORMAT(fap_calc.writeoff_date, '%Y%m')");
                    }
                )
                ->where('fap_calc.period', $calculationPeriod)
                ->whereNotNull('fap_calc.writeoff_status')
                ->whereNotNull('fap_calc.writeoff_date')
                // Filter 5 tahun ke belakang dari periode perhitungan (Poin 2)
                ->where('fap_calc.writeoff_date', '>=', $windowStartDate)
                // Akad 03 hanya jika sudah JTP saat writeoff — Ref: PRD Bab 9
                ->where(function ($q): void {
                    $q->where('fa.akad_code', '!=', '03')
                        ->orWhere(function ($q2): void {
                            $q2->where('fa.akad_code', '03')
                                ->whereNotNull('fap_wo.maturity_date')
                                ->whereRaw('fap_wo.maturity_date <= fap_calc.writeoff_date');
                        });
                })
                ->selectRaw('YEAR(fap_calc.writeoff_date) as year, SUM(fap_wo.outstanding_balance) as total'),
            $akadCodes,
            'fa.akad_code'
        );

        if ($usageType !== null) {
            $query->where('fa.usage_type', $usageType->value);
        }

        // Segmentasi level 1: pecahan per kode kantor — Ref: PRD Bab 5
        if ($officeCode !== null) {
            $query->where('fa.office_code', $officeCode);
        }

        // Segmentasi level 2: pecahan per kode akad — Ref: PRD Bab 5
        if ($akadCode !== null) {
            $query->where('fa.akad_code', $akadCode);
        }

        return $query->groupBy('year')
            ->orderBy('year')
            ->pluck('total', 'year')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * Aggregate total recovery per tahun dari financing_account_periods.
     * Logika (Ref: catatan perbaikan 2026-08-26):
     *   Step 1: Ambil akun dengan writeoff_status NOT NULL pada periode perhitungan (Poin 1).
     *   Step 2: Filter yang writeoff_date-nya dalam 5 tahun ke belakang (Poin 2).
     *   Step 3: Join ke baris periode = DATE_FORMAT(writeoff_date, '%Y%m') untuk outstanding saat WO (Poin 3).
     *   Step 4: Recovery = outstanding_writeoff - outstanding_periode_perhitungan.
     *           Jika akun tidak muncul di periode perhitungan → outstanding = 0 → recovery = outstanding_writeoff (Poin 4).
     * Tahun recovery dikategorikan berdasarkan YEAR(writeoff_date) agar selaras dengan writeoffByYear.
     *
     * @return array<string, float>
     */
    private function aggregateRecoveryByYear(?UsageType $usageType, string $calculationPeriod, string $windowStartDate, ?array $akadCodes = null, ?string $officeCode = null, ?string $akadCode = null): array
    {
        // Step 1-3: ambil daftar akun WO pada periode perhitungan beserta outstanding saat WO
        $writeoffQuery = AkadEligibilityService::restrict(
            DB::table('financing_account_periods as fap_calc')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap_calc.financing_account_id')
                // join ke baris periode = DATE_FORMAT(writeoff_date, '%Y%m') untuk outstanding saat WO
                ->joinSub(
                    DB::table('financing_account_periods')
                        ->select(['financing_account_id', 'period', 'outstanding_balance', 'writeoff_date', 'maturity_date']),
                    'fap_wo',
                    function ($join): void {
                        $join->on('fap_wo.financing_account_id', '=', 'fap_calc.financing_account_id')
                            ->whereRaw("fap_wo.period = DATE_FORMAT(fap_calc.writeoff_date, '%Y%m')");
                    }
                )
                ->where('fap_calc.period', $calculationPeriod)
                ->whereNotNull('fap_calc.writeoff_status')
                ->whereNotNull('fap_calc.writeoff_date')
                ->where('fap_calc.writeoff_date', '>=', $windowStartDate)
                ->where(function ($q): void {
                    $q->where('fa.akad_code', '!=', '03')
                        ->orWhere(function ($q2): void {
                            $q2->where('fa.akad_code', '03')
                                ->whereNotNull('fap_wo.maturity_date')
                                ->whereRaw('fap_wo.maturity_date <= fap_calc.writeoff_date');
                        });
                })
                ->select([
                    'fap_calc.financing_account_id',
                    DB::raw('YEAR(fap_calc.writeoff_date) as writeoff_year'),
                    DB::raw('fap_wo.outstanding_balance as outstanding_writeoff'),
                ]),
            $akadCodes,
            'fa.akad_code'
        );

        if ($usageType !== null) {
            $writeoffQuery->where('fa.usage_type', $usageType->value);
        }

        // Segmentasi level 1: pecahan per kode kantor — Ref: PRD Bab 5
        if ($officeCode !== null) {
            $writeoffQuery->where('fa.office_code', $officeCode);
        }

        // Segmentasi level 2: pecahan per kode akad — Ref: PRD Bab 5
        if ($akadCode !== null) {
            $writeoffQuery->where('fa.akad_code', $akadCode);
        }

        $writeoffAccounts = $writeoffQuery->get();

        if ($writeoffAccounts->isEmpty()) {
            return [];
        }

        // Step 4a: ambil outstanding akun WO pada periode perhitungan (untuk hitung recovery)
        $accountIds = $writeoffAccounts->pluck('financing_account_id')->unique()->toArray();
        $currentOutstandingMap = DB::table('financing_account_periods')
            ->whereIn('financing_account_id', $accountIds)
            ->where('period', $calculationPeriod)
            ->pluck('outstanding_balance', 'financing_account_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // Step 4b: hitung recovery per akun, kelompokkan per tahun writeoff
        $recoveryByYear = [];
        foreach ($writeoffAccounts as $row) {
            $year = (string) $row->writeoff_year;
            $woOutstanding = (float) $row->outstanding_writeoff;
            $currentOutstanding = $currentOutstandingMap[$row->financing_account_id] ?? 0.0;
            // Jika tidak muncul di periode perhitungan → current = 0 → recovery = 100%
            $recovery = max(0.0, $woOutstanding - $currentOutstanding);
            $recoveryByYear[$year] = ($recoveryByYear[$year] ?? 0.0) + $recovery;
        }

        ksort($recoveryByYear);

        return $recoveryByYear;
    }

    /** Akhir bulan dari periode yyyymm sebagai tanggal Y-m-d. */
    private function periodEndDate(string $period): string
    {
        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 4, 2);

        return date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
    }
}
