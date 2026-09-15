<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Ckpn\Lgd\ExpectedRecoveries\LgdExpectedRecoveriesCalculator;
use App\Enums\UsageType;
use App\Models\CkpnPeriod;
use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Support\Facades\DB;

/**
 * Hitung LGD Expected Recoveries (ER) detail secara on-the-fly untuk tampilan pivot UI.
 *
 * LGD ER mengukur tingkat kerugian berdasarkan sejarah pemulihan (recovery) dari akun
 * yang pernah di-write-off dalam rolling window tahun tertentu:
 *   Recovery Rate per tahun  = total_recovery_tahun / total_writeoff_tahun
 *   Expected Recovery Rate   = rata-rata Recovery Rate selama window (default 5 tahun)
 *   LGD Rate (ER)            = 1 − Expected Recovery Rate
 *
 * Definisi Recovery (per akun WO):
 *   Recovery = max(0, outstanding_saat_WO − outstanding_saat_perhitungan)
 *   → artinya: berapa yang sudah terbayar sejak tanggal write-off
 *
 * Kriteria akun masuk populasi LGD ER:
 *   - write-off terjadi dalam rolling window [calculationPeriod − windowYears, calculationPeriod]
 *   - akun terdaftar di financing_account_periods pada periode write-off & periode perhitungan
 *   - opsional filter per usage_type (segmen), atau all-account jika null
 *
 * LGD ER berlaku untuk semua akun CKPN Kolektif non-Write-Off (kol. 1–4).
 * Untuk akun kol. 5 (WO aktif), digunakan LGD CS (Collateral Shortfall).
 *
 * Service ini menghitung on-the-fly (tidak membaca snapshot lgd_expected_recoveries_results).
 * Gunakan untuk drill-down pivot di UI; untuk kalkulasi resmi gunakan LgdErCalculationJob.
 *
 * Ref: PRD Bab 9
 */
final class LgdErDetailService
{
    /**
     * Periode CKPN yang tersedia sebagai opsi input perhitungan LGD ER.
     *
     * Sumber data: tabel ckpn_periods — periode yang terdaftar sebagai periode CKPN resmi.
     * Diurutkan dari periode terbaru ke terlama untuk kemudahan pilihan UI.
     *
     * @return string[] Array periode format yyyymm, mis. ['202506', '202503', '202412']
     */
    public function availablePeriods(): array
    {
        return CkpnPeriod::orderedPeriods();
    }

    /**
     * Periode yang sudah punya snapshot LGD ER tersimpan di tabel lgd_expected_recoveries_results.
     *
     * Digunakan untuk memfilter pilihan periode di UI tabel hasil — hanya tampilkan periode
     * yang sudah pernah dijalankan job kalkulasi LGD ER (LgdErCalculationJob).
     * Berbeda dengan availablePeriods() yang menampilkan semua periode CKPN terdaftar.
     *
     * @return string[] Array periode format yyyymm, hanya periode yang sudah ada snapshotnya
     */
    public function snapshotPeriods(): array
    {
        return LgdExpectedRecoveriesResult::distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period')
            ->toArray();
    }

    /**
     * Hitung pivot detail LGD ER on-the-fly untuk satu periode kalkulasi dan opsional satu segmen.
     *
     * Alur kalkulasi:
     *   1. Inisialisasi LgdExpectedRecoveriesCalculator dengan windowYears (default 5)
     *   2. Jika $usageTypeValue null → aktifkan flag useAllAccount=true (gabungkan semua segmen)
     *   3. Panggil calculateWithDetails() → data per tahun WO, recovery, dan rate final
     *   4. Hitung recovery_rate_by_year = recovery / writeoff per tahun (untuk tampilan tabel)
     *   5. Return array lengkap dengan rentang window dan breakdown per tahun
     *
     * Formula:
     *   Recovery Rate per tahun  = recovery_tahun / writeoff_tahun  (capped 1.0)
     *   Expected Recovery Rate   = average(recovery_rate_per_tahun selama window)
     *   LGD Rate (ER)            = 1 − Expected Recovery Rate
     *
     * Catatan: windowYears hardcode 5 di service ini (untuk preview UI).
     * Job resmi (LgdErCalculationJob) membaca windowYears dari calculation_parameters.
     *
     * @param  string  $calculationPeriod  Format yyyymm — periode target perhitungan
     * @param  string|null  $usageTypeValue  Integer UsageType sebagai string; null = all-account
     * @return array{
     *   calculation_period: string,
     *   window_start_date: string,       ← tanggal awal rolling window (calculationPeriod − 5 tahun)
     *   window_end_date: string,         ← tanggal akhir rolling window (hari terakhir calculationPeriod)
     *   window_years: int,               ← panjang window dalam tahun
     *   is_all_account: bool,            ← true jika gabungan semua segmen
     *   years: string[],                 ← daftar tahun yang muncul di WO atau recovery
     *   writeoff_by_year: array<string,float>,         ← total outstanding WO per tahun
     *   recovery_by_year: array<string,float>,         ← total recovery per tahun
     *   recovery_rate_by_year: array<string,float>,    ← recovery/writeoff per tahun (0–1)
     *   expected_recovery_rate: float,   ← rata-rata recovery rate selama window
     *   lgd_rate: float,                 ← 1 − expected_recovery_rate
     *   total_writeoff: float,
     *   total_recovery: float,
     * }
     */
    public function calculate(string $calculationPeriod, ?string $usageTypeValue): array
    {
        $windowYears = 5;
        $calculator = new LgdExpectedRecoveriesCalculator(windowYears: $windowYears);

        $usageType = $usageTypeValue !== null
            ? UsageType::from((int) $usageTypeValue)
            : UsageType::ModalKerja; // placeholder — akan di-override fallback all-account

        // Jika usageTypeValue null → paksa all-account lewat flag
        if ($usageTypeValue === null) {
            $calculator = new LgdExpectedRecoveriesCalculator(windowYears: $windowYears, useAllAccount: true);
            $usageType = UsageType::ModalKerja; // nilai tidak dipakai karena useAllAccount=true
        }

        $details = $calculator->calculateWithDetails($usageType, $calculationPeriod);

        $year = (int) substr($calculationPeriod, 0, 4);
        $month = (int) substr($calculationPeriod, 4, 2);
        $endDate = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        $startDate = date('Y-m-d', strtotime("-{$windowYears} years", strtotime($endDate)));

        // Gabungkan tahun dari writeoff maupun recovery agar semua tahun tampil di tabel
        $years = array_unique(array_merge(
            array_keys($details['writeoff_by_year']),
            array_keys($details['recovery_by_year']),
        ));
        sort($years);

        // Hitung recovery rate per tahun utk tampilan detail
        $recoveryRateByYear = [];
        foreach ($years as $yr) {
            $wo = $details['writeoff_by_year'][$yr] ?? 0.0;
            $rc = $details['recovery_by_year'][$yr] ?? 0.0;
            $recoveryRateByYear[$yr] = $wo > 0 ? min(1.0, $rc / $wo) : 0.0;
        }

        return [
            'calculation_period' => $calculationPeriod,
            'window_start_date' => $startDate,
            'window_end_date' => $endDate,
            'window_years' => $windowYears,
            'is_all_account' => $details['is_all_account'],
            'years' => $years,
            'writeoff_by_year' => $details['writeoff_by_year'],
            'recovery_by_year' => $details['recovery_by_year'],
            'recovery_rate_by_year' => $recoveryRateByYear,
            'expected_recovery_rate' => $details['expected_recovery_rate'],
            'lgd_rate' => $details['lgd_rate'],
            'total_writeoff' => $details['total_writeoff'],
            'total_recovery' => $details['total_recovery'],
        ];
    }

    /**
     * Daftar debitur write-off yang menjadi basis populasi LGD ER untuk periode & segmen tertentu.
     *
     * Mengembalikan detail per akun WO dalam rolling window 5 tahun dari calculationPeriod,
     * lengkap dengan outstanding saat WO dan outstanding saat periode perhitungan (untuk recovery).
     *
     * Kriteria akun masuk daftar:
     *   - writeoff_status NOT NULL dan writeoff_date NOT NULL pada periode calculationPeriod
     *   - writeoff_date >= (hari terakhir calculationPeriod − 5 tahun)
     *   - Opsional: filter usage_type jika $usageTypeValue diisi
     *
     * Cara baca outstanding_writeoff:
     *   Bukan saldo saat periode perhitungan, melainkan saldo pada periode = DATE_FORMAT(writeoff_date, 'yyyymm')
     *   → yaitu saldo pokok pada bulan terjadinya write-off (sebelum dihapus dari neraca)
     *
     * Cara hitung recovery per akun:
     *   recovery = max(0, outstanding_writeoff − current_outstanding)
     *   current_outstanding = outstanding pada periode calculationPeriod (berapa yang sudah terbayar kembali)
     *
     * @param  string  $calculationPeriod  Format yyyymm
     * @param  string|null  $usageTypeValue  Integer UsageType sebagai string; null = semua segmen
     * @return Collection<int, object{
     *   financing_account_id: int,
     *   account_number: string,
     *   customer_name: string,
     *   usage_type: int,
     *   writeoff_date: string,
     *   writeoff_year: int,
     *   outstanding_writeoff: float,   ← saldo saat bulan write-off terjadi
     *   current_outstanding: float,    ← saldo saat calculationPeriod (angka terbayar = selisihnya)
     *   recovery: float,               ← max(0, outstanding_writeoff − current_outstanding)
     * }>
     */
    public function debtorList(string $calculationPeriod, ?string $usageTypeValue): Collection
    {
        $windowYears = 5;
        $year = (int) substr($calculationPeriod, 0, 4);
        $month = (int) substr($calculationPeriod, 4, 2);
        $endDate = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        $windowStartDate = date('Y-m-d', strtotime("-{$windowYears} years", strtotime($endDate)));

        // Poin 1: sumber WO dari period = calculationPeriod dengan writeoff_status NOT NULL
        // Poin 2: filter writeoff_date dalam 5 tahun ke belakang
        // Poin 3: outstanding_writeoff dari baris period = DATE_FORMAT(writeoff_date, '%Y%m')
        $writeoffAccounts = DB::table('financing_account_periods as fap_calc')
            ->join('financing_accounts as fa', 'fap_calc.financing_account_id', '=', 'fa.id')
            ->joinSub(
                DB::table('financing_account_periods')
                    ->select(['financing_account_id', 'period', 'outstanding_balance']),
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
            ->when($usageTypeValue !== null, fn ($q) => $q->where('fa.usage_type', (int) $usageTypeValue))
            ->select([
                'fap_calc.financing_account_id',
                'fa.account_number',
                'fa.customer_name',
                'fa.usage_type',
                'fap_calc.writeoff_date',
                DB::raw('YEAR(fap_calc.writeoff_date) as writeoff_year'),
                DB::raw('fap_wo.outstanding_balance as outstanding_writeoff'),
            ])
            ->orderBy('fap_calc.writeoff_date')
            ->get();

        // Poin 4: outstanding pada periode perhitungan untuk hitung recovery
        $currentOutstandingMap = DB::table('financing_account_periods')
            ->where('period', $calculationPeriod)
            ->pluck('outstanding_balance', 'financing_account_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        return $writeoffAccounts->map(function ($row) use ($currentOutstandingMap) {
            $woOutstanding = (float) $row->outstanding_writeoff;
            $currentOutstanding = $currentOutstandingMap[$row->financing_account_id] ?? 0.0;
            $row->current_outstanding = $currentOutstanding;
            $row->recovery = max(0.0, $woOutstanding - $currentOutstanding);

            return $row;
        });
    }
}
