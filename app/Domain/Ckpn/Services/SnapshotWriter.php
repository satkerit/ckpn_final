<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use App\Models\LgdCollateralShortfallBySegmentResult;
use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\LgdFinalResult;
use App\Models\PdMigrationMatrix;
use App\Models\PdMigrationResult;
use App\Models\PdNetflowBucketMovement;
use App\Models\PdNetflowCalculationHistory;
use App\Models\PdNetflowCompoundRate;
use App\Models\PdNetflowConsolidated;
use App\Models\PdNetflowInvestasi;
use App\Models\PdNetflowKonsumsi;
use App\Models\PdNetflowModalKerja;
use App\Models\PdNetflowResult;

/**
 * Satu-satunya class yang boleh menulis ke tabel snapshot hasil kalkulasi CKPN.
 *
 * Prinsip Snapshot Immutability (Ref: PRD Bab 13.2 & AGENTS.md §4):
 *   - Semua method bersifat INSERT-ONLY — tidak ada UPDATE atau DELETE pada data snapshot.
 *   - Setiap snapshot terikat pada satu CalculationRunLog (run_log_id) sebagai jejak audit.
 *   - Snapshot periode yang sudah berstatus Completed TIDAK BOLEH ditimpa.
 *   - Pengecualian satu-satunya: writeCkpnIndividualResults() menghapus draft non-Completed
 *     sebelum insert ulang (lihat PHPDoc method tersebut).
 *
 * Urutan panggilan yang benar untuk satu siklus kalkulasi:
 *   1. writePdNetflowResult()   atau writePdMigrationResult()
 *   2. writeLgdErResult()       + writeLgdCsResults() + writeLgdCsBySegmentResult()
 *   3. writeLgdFinalResult()
 *   4. writeCkpnCollectiveResults() atau writeCkpnIndividualResults()
 *
 * Ref: PRD Bab 15 (daftar tabel snapshot), AGENTS.md §4 (aturan koding snapshot)
 */
final class SnapshotWriter
{
    /**
     * Simpan hasil PD Netflow final (pd_rate per bucket) ke tabel snapshot.
     * Ref: PRD Bab 7 — snapshot immutability (insert-only)
     *
     * Satu baris per bucket per segmen per periode.
     * Dipanggil oleh job CalculatePdNetflow setelah semua kalkulasi selesai.
     *
     * Prasyarat: CalculationRunLog sudah dibuat dan statusnya sedang Running.
     *
     * @param  string  $calculationPeriod  Format yyyymm
     * @param  string  $dataStart  Periode awal data outstanding yang dipakai (yyyymm)
     * @param  string  $dataEnd  Periode akhir data outstanding yang dipakai (yyyymm)
     * @param  int  $windowMonths  Panjang rolling window dalam bulan
     * @param  array<int, float>  $pdRates  Key = bucket_id, value = pd_rate (0.0–1.0)
     */
    public function writePdNetflowResult(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        string $dataStart,
        string $dataEnd,
        int $windowMonths,
        array $pdRates,
        ?string $notes = null,
    ): void {
        foreach ($pdRates as $bucketId => $pdRate) {
            PdNetflowResult::create([
                'calculation_run_log_id' => $runLog->id,
                'usage_type' => $usageType->value,
                'from_bucket_id' => $bucketId,
                'calculation_period' => $calculationPeriod,
                'pd_rate' => $pdRate,
                'data_period_start' => $dataStart,
                'data_period_end' => $dataEnd,
                'window_months' => $windowMonths,
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Simpan detail intermediate PD Netflow: bucket movement dan compound flow loss per bucket.
     * Ref: PRD Bab 7
     *
     * Data ini digunakan untuk keperluan audit trail dan rekonstruksi kalkulasi —
     * bukan dipakai langsung dalam CKPN. Terdiri dari dua jenis record:
     *
     * 1. BucketMovement (pd_netflow_bucket_movements):
     *    Satu baris per bucket per periode = persentase akun yang bergerak dari bucket tsb
     *    ke bucket lebih buruk (atau write-off) dalam satu bulan.
     *    - transition_rate  : tingkat pergerakan bucket bulan bersangkutan (0.0–1.0, di-clamp)
     *    - is_projected     : true jika periode tersebut adalah hasil proyeksi (belum ada data aktual)
     *    - source_outstanding   : outstanding awal periode (dari bulan sebelumnya)
     *    - destination_outstanding : outstanding akhir periode (bulan bersangkutan)
     *
     * 2. CompoundRate (pd_netflow_compound_rates):
     *    Akumulasi perkalian transition_rate dari start_period hingga akhir window.
     *    Dipakai PdNetflowCalculator untuk menghitung pd_rate final per bucket.
     *
     * @param  array<int, array<string, float>>  $transitionRates  [bucket_id][period] = rate per bulan
     * @param  array<int, array<string, float>>  $compoundRates  [bucket_id][start_period] = compound rate
     * @param  array<string, array<int, float>>  $outstandingMap  [period][bucket_id] = outstanding
     * @param  string[]  $projPeriods  Daftar periode proyeksi (yyyymm)
     * @param  string  $rateStart  Periode awal window transition rate (yyyymm)
     * @param  string  $compoundEnd  Periode akhir compound rate (= calculationPeriod)
     */
    public function writePdNetflowDetail(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $transitionRates,
        array $compoundRates,
        array $outstandingMap,
        array $projPeriods,
        string $rateStart,
        string $compoundEnd,
    ): void {
        $projPeriodSet = array_flip($projPeriods);

        // Simpan bucket movement: outstanding + transition rate per bucket per periode
        foreach ($transitionRates as $bucketId => $periods) {
            foreach ($periods as $period => $rate) {
                $period = (string) $period;
                $prevPeriod = PeriodHelper::shiftBack($period, 1);
                PdNetflowBucketMovement::create([
                    'calculation_run_log_id' => $runLog->id,
                    'usage_type' => $usageType->value,
                    'from_bucket_id' => $bucketId,
                    'period' => $period,
                    'transition_rate' => min(1.0, max(0.0, (float) $rate)),
                    'is_projected' => isset($projPeriodSet[$period]),
                    'source_outstanding' => $outstandingMap[$prevPeriod][$bucketId] ?? 0.0,
                    'destination_outstanding' => $outstandingMap[$period][$bucketId] ?? 0.0,
                ]);
            }
        }

        // Simpan compound flow loss per bucket per start_period
        foreach ($compoundRates as $bucketId => $periods) {
            foreach ($periods as $startPeriod => $rate) {
                PdNetflowCompoundRate::create([
                    'calculation_run_log_id' => $runLog->id,
                    'usage_type' => $usageType->value,
                    'from_bucket_id' => $bucketId,
                    'start_period' => $startPeriod,
                    'compound_rate' => $rate,
                ]);
            }
        }
    }

    /**
     * Simpan raw history rows yang dipakai dalam kalkulasi PD Netflow ke tabel audit.
     * Ref: PRD Bab 7
     *
     * Digunakan sebagai rekam jejak data outstanding per bucket per periode yang menjadi
     * input aktual kalkulasi. Disimpan sebagai satu record JSON (history_data) per
     * calculationPeriod + usageType + runLog — bukan baris per periode/bucket.
     *
     * Berguna untuk reproduksi ulang kalkulasi tanpa mengubah data staging yang mungkin
     * sudah berubah di kemudian hari.
     *
     * @param  array<int, array<string, mixed>>  $history  Snapshot data input kalkulasi
     */
    public function writePdNetflowHistory(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $history,
    ): void {
        PdNetflowCalculationHistory::create([
            'calculation_run_log_id' => $runLog->id,
            'calculation_period' => $calculationPeriod,
            'usage_type' => $usageType->value,
            'history_data' => $history,
        ]);
    }

    /**
     * Simpan hasil LGD Expected Recoveries per segmen ke tabel snapshot.
     * Ref: PRD Bab 9 — insert-only, satu baris per segmen per periode.
     *
     * Konsep: LGD ER mengukur kerugian riil dari akun yang sudah write-off dalam window
     * historis. Formula: LGD = (Total OS WO − Total Recovery) / Total OS WO.
     *
     * Penjelasan parameter penting:
     *   - $usageType     : null jika hasil ini adalah fallback "all-account" (semua segmen
     *                      digabung), isi UsageType jika hasil per segmen spesifik.
     *   - $windowYears   : panjang window historis recovery (default 5 tahun, Ref: PRD Bab 9.2)
     *   - $lgdRate       : 1 − expectedRecoveryRate, sudah di-clamp ke [0.0, 1.0]
     *   - $expectedRecoveryRate : rata-rata rasio pemulihan = Total Recovery / Total OS WO
     *   - $isAllAccount  : true jika usageType=null (fallback all-account dipicu karena
     *                      data segmen tidak cukup)
     *
     * Dipanggil oleh job CalculateLgdExpectedRecoveries, sebelum CalculateLgdFinal.
     */
    public function writeLgdErResult(
        CalculationRunLog $runLog,
        ?UsageType $usageType,
        string $calculationPeriod,
        string $dataStart,
        string $dataEnd,
        int $windowYears,
        float $lgdRate,
        float $expectedRecoveryRate,
        float $totalWriteoff,
        float $totalRecovery,
        bool $isAllAccount,
        ?string $notes = null,
    ): void {
        LgdExpectedRecoveriesResult::create([
            'calculation_run_log_id' => $runLog->id,
            'usage_type' => $usageType?->value,
            'calculation_period' => $calculationPeriod,
            'data_period_start' => $dataStart,
            'data_period_end' => $dataEnd,
            'window_years' => $windowYears,
            'total_writeoff_amount' => $totalWriteoff,
            'total_recovery_amount' => $totalRecovery,
            'expected_recovery_rate' => $expectedRecoveryRate,
            'lgd_rate' => $lgdRate,
            'is_all_account' => $isAllAccount,
            'notes' => $notes,
        ]);
    }

    /**
     * Simpan hasil LGD Collateral Shortfall level akun ke tabel snapshot.
     * Ref: PRD Bab 10 — insert-only, satu baris per akun per periode.
     *
     * Hanya akun yang memenuhi 5 kriteria eligibilitas yang masuk ke sini:
     *   1. Kolektibilitas = 5 (macet) atau berstatus write-off
     *   2. Memiliki data agunan aktif (is_active = true)
     *   3. Data akad aktif pada periode bersangkutan
     *   4. Akad termasuk dalam daftar eligible CKPN (bukan akad yang dikecualikan)
     *   5. Nilai agunan net > 0 (ada agunan yang bisa diperhitungkan)
     *
     * Penjelasan field:
     *   - outstanding_balance    : EAD akun pada periode bersangkutan
     *   - collateral_net_value   : nilai likuidasi agunan setelah diskon
     *   - shortfall              : MAX(0, outstanding − collateral_net_value)
     *   - lgd_rate               : shortfall / outstanding_balance (0.0–1.0)
     *
     * Dipanggil oleh job CalculateLgdCollateralShortfall, sebelum writeLgdCsBySegmentResult().
     *
     * @param  array<int, array{financing_account_id: int, outstanding_balance: float, collateral_net_value: float, shortfall: float, lgd_rate: float}>  $accountResults
     */
    public function writeLgdCsResults(
        CalculationRunLog $runLog,
        ?UsageType $usageType,
        string $calculationPeriod,
        array $accountResults,
        ?string $notes = null,
    ): void {
        foreach ($accountResults as $result) {
            LgdCollateralShortfallResult::create([
                'calculation_run_log_id' => $runLog->id,
                'financing_account_id' => $result['financing_account_id'],
                'usage_type' => $usageType?->value,
                'calculation_period' => $calculationPeriod,
                'outstanding_balance' => $result['outstanding_balance'],
                'collateral_net_value' => $result['collateral_net_value'],
                'shortfall' => $result['shortfall'],
                'lgd_rate' => $result['lgd_rate'],
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Simpan ringkasan agregat LGD Collateral Shortfall per segmen ke tabel snapshot.
     * Ref: PRD Bab 10 — insert-only, satu baris per segmen per periode.
     *
     * Dipanggil setelah writeLgdCsResults() — data agregat ini dihitung oleh
     * LgdCollateralShortfallCalculator::aggregate() dari kumpulan hasil per akun.
     *
     * Penjelasan field agregat:
     *   - account_count          : jumlah akun eligible yang masuk perhitungan
     *   - total_outstanding      : total EAD semua akun eligible
     *   - total_collateral_net_value : total nilai agunan net (setelah diskon) semua akun
     *   - total_shortfall        : total kekurangan agunan = SUM(MAX(0, OS − collateral))
     *   - avg_lgd_rate           : rata-rata sederhana lgd_rate per akun (simple average,
     *                              BUKAN weighted — konsisten dengan PRD Bab 10)
     *
     * Record ini yang dipakai LgdFinalCalculator sebagai input komponen CS.
     *
     * @param  array{account_count: int, total_outstanding: float, total_collateral_net_value: float, total_shortfall: float, avg_lgd_rate: float}  $aggregate
     */
    public function writeLgdCsBySegmentResult(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $aggregate,
        ?string $notes = null,
    ): void {
        LgdCollateralShortfallBySegmentResult::create([
            'calculation_run_log_id' => $runLog->id,
            'usage_type' => $usageType->value,
            'calculation_period' => $calculationPeriod,
            'account_count' => $aggregate['account_count'],
            'total_outstanding' => $aggregate['total_outstanding'],
            'total_collateral_net_value' => $aggregate['total_collateral_net_value'],
            'total_shortfall' => $aggregate['total_shortfall'],
            'avg_lgd_rate' => $aggregate['avg_lgd_rate'],
            'notes' => $notes,
        ]);
    }

    /**
     * Simpan hasil CKPN Individual per akun ke tabel snapshot.
     * Ref: PRD Bab 6.1 — CKPN Individual = MAX(0, Outstanding − Nilai Likuidasi Agunan Bersih)
     *
     * ⚠️ PERILAKU BERBEDA dari method lain: BUKAN murni insert-only.
     * Untuk setiap akun, method ini menjalankan logika berikut:
     *   1. Hapus snapshot lama untuk akun+periode ini jika run_log terkait belum berstatus Completed
     *      (artinya: hapus draft kalkulasi sebelumnya yang belum disetujui).
     *   2. Skip insert jika sudah ada snapshot Completed untuk akun+periode ini
     *      (proteksi immutability — snapshot yang disetujui tidak boleh ditimpa).
     *   3. Insert snapshot baru jika lolos kedua pemeriksaan di atas.
     *
     * Penjelasan field hasil per akun:
     *   - outstanding_balance             : saldo pokok akun pada periode bersangkutan (= EAD)
     *   - total_collateral_liquidation_value : total nilai likuidasi agunan aktif (SUM estimated_sale_value)
     *   - selling_cost_rate               : persentase biaya jual dari calculation_parameters
     *   - selling_cost_amount             : selling_cost_rate × total_collateral_liquidation_value
     *   - ckpn_amount                     : MAX(0, outstanding − (total_liquidation − selling_cost))
     *   - collectibility                  : kolektibilitas akun pada periode bersangkutan
     *
     * Dipanggil oleh job CalculateCkpnIndividual.
     *
     * @param  array<int, array{financing_account_id: int, outstanding_balance: float, total_collateral_liquidation_value: float, selling_cost_rate: float, selling_cost_amount: float, ckpn_amount: float, collectibility: int}>  $results
     */
    public function writeCkpnIndividualResults(
        CalculationRunLog $runLog,
        string $calculationPeriod,
        array $results,
        ?string $notes = null,
    ): void {
        foreach ($results as $result) {
            // Hapus snapshot lama yang belum Completed agar tidak trigger immutability guard
            CkpnIndividualResult::where('financing_account_id', $result['financing_account_id'])
                ->where('calculation_period', $calculationPeriod)
                ->whereDoesntHave('calculationRunLog', fn ($q) => $q->where('status', RunStatus::Completed->value))
                ->delete();

            // Skip jika sudah ada snapshot Completed untuk akun+periode ini
            if (CkpnIndividualResult::where('financing_account_id', $result['financing_account_id'])
                ->where('calculation_period', $calculationPeriod)
                ->exists()
            ) {
                continue;
            }

            CkpnIndividualResult::create([
                'calculation_run_log_id' => $runLog->id,
                'financing_account_id' => $result['financing_account_id'],
                'calculation_period' => $calculationPeriod,
                'outstanding_balance' => $result['outstanding_balance'],
                'total_collateral_liquidation_value' => $result['total_collateral_liquidation_value'],
                'selling_cost_rate' => $result['selling_cost_rate'],
                'selling_cost_amount' => $result['selling_cost_amount'],
                'ckpn_amount' => $result['ckpn_amount'],
                'collectibility' => $result['collectibility'],
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Simpan hasil CKPN Kolektif per akun ke tabel snapshot.
     * Ref: PRD Bab 11 — CKPN Kolektif = PD × LGD × EAD, insert-only.
     *
     * Satu baris per akun per periode. Dipanggil oleh job CalculateCkpnCollective
     * setelah CalculateLgdFinal selesai.
     *
     * Penjelasan field per akun:
     *   - pd_method_used   : 'netflow' atau 'migration' — metode PD yang dipakai akun ini
     *   - pd_rate          : PD rate yang dipilih berdasarkan bucket/quality_grade akun
     *   - lgd_method_used  : 'expected_recoveries', 'collateral_shortfall', atau 'final'
     *   - lgd_rate         : LGD Final rate segmen (satu rate berlaku untuk seluruh akun segmen)
     *   - ead              : Exposure at Default = outstanding_balance periode bersangkutan
     *   - ckpn_amount      : pd_rate × lgd_rate × ead
     *   - usage_type       : segmen akun (sesuai tabel risk_segments)
     *
     * Prasyarat: snapshot LGD Final (writeLgdFinalResult) untuk segmen sudah tersedia.
     *
     * @param  array<int, array{financing_account_id: int, usage_type: string, pd_method_used: string, pd_rate: float, lgd_method_used: string, lgd_rate: float, ead: float, ckpn_amount: float}>  $results
     */
    public function writeCkpnCollectiveResults(
        CalculationRunLog $runLog,
        string $calculationPeriod,
        array $results,
        ?string $notes = null,
    ): void {
        foreach ($results as $result) {
            CkpnCollectiveResult::create([
                'calculation_run_log_id' => $runLog->id,
                'financing_account_id' => $result['financing_account_id'],
                'usage_type' => $result['usage_type'],
                'calculation_period' => $calculationPeriod,
                'pd_method_used' => $result['pd_method_used'],
                'pd_rate' => $result['pd_rate'],
                'lgd_method_used' => $result['lgd_method_used'],
                'lgd_rate' => $result['lgd_rate'],
                'ead' => $result['ead'],
                'pd_bucket_id' => $result['pd_bucket_id'] ?? null,
                'pd_quality_grade_id' => $result['pd_quality_grade_id'] ?? null,
                'ckpn_amount' => $result['ckpn_amount'],
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Simpan hasil LGD Final gabungan ER + CS per segmen ke tabel snapshot.
     * Ref: PRD Bab 9 & 10 — insert-only, satu baris per segmen per periode.
     *
     * LGD Final menggabungkan dua metode:
     *   - Expected Recoveries (ER): mengukur kerugian dari write-off historis
     *   - Collateral Shortfall (CS): mengukur kekurangan coverage agunan akun macet
     *
     * Formula:
     *   Total WO  = er_total_writeoff_amount + cs_total_outstanding
     *   Total LGD = (er_total_writeoff_amount − er_total_recovery_amount) + cs_total_shortfall
     *   LGD Final = MAX(0, MIN(1, Total LGD / Total WO))
     *
     * Penjelasan field $result:
     *   - er_total_writeoff_amount  : total OS akun WO dalam window ER (basis denominasi ER)
     *   - er_total_recovery_amount  : total pemulihan aktual dari akun WO tersebut
     *   - cs_total_outstanding      : total OS akun kol.5+WO dengan agunan (basis denominasi CS)
     *   - cs_total_shortfall        : total shortfall agunan (bagian outstanding yang tidak tertutup agunan)
     *   - total_lgd                 : kerugian gabungan (numerator)
     *   - total_wo                  : total exposure gabungan (denominator)
     *   - lgd_final_rate            : lgd_final_rate = total_lgd / total_wo, di-clamp [0.0, 1.0]
     *
     * Prasyarat: writeLgdErResult() dan writeLgdCsBySegmentResult() sudah dipanggil lebih dulu.
     *
     * @param array{
     *   er_total_writeoff_amount: float,
     *   er_total_recovery_amount: float,
     *   cs_total_outstanding: float,
     *   cs_total_shortfall: float,
     *   total_lgd: float,
     *   total_wo: float,
     *   lgd_final_rate: float,
     * } $result
     */
    public function writeLgdFinalResult(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $result,
    ): void {
        LgdFinalResult::create([
            'calculation_run_log_id' => $runLog->id,
            'usage_type' => $usageType->value,
            'calculation_period' => $calculationPeriod,
            'er_total_writeoff_amount' => $result['er_total_writeoff_amount'],
            'er_total_recovery_amount' => $result['er_total_recovery_amount'],
            'cs_total_outstanding' => $result['cs_total_outstanding'],
            'cs_total_shortfall' => $result['cs_total_shortfall'],
            'total_recover' => $result['total_lgd'],
            'total_os' => $result['total_wo'],
            'lgd_final_rate' => $result['lgd_final_rate'],
        ]);
    }

    /**
     * Simpan hasil PD Migration per quality_grade ke tabel snapshot.
     * Ref: PRD Bab 8 — insert-only, satu baris per quality_grade per segmen per periode.
     *
     * PD Migration menggunakan pendekatan cohort triwulanan: akun ditelusuri dari satu
     * quality_grade awal ke posisi kualitas 12 bulan kemudian (termasuk write-off).
     * Cohort yang valid adalah triwulan (Maret/Juni/September/Desember) karena kualitas
     * kredit umumnya ditetapkan per kuartal.
     *
     * Penjelasan parameter:
     *   - $dataStart     : periode cohort paling awal yang dipakai (yyyymm, harus triwulan)
     *   - $dataEnd       : periode akhir window cohort (yyyymm)
     *   - $cohortCount   : jumlah cohort yang berhasil dihitung (≥1 agar valid)
     *   - $pdRates       : key = quality_grade_id, value = PD rate rata-rata lintas cohort (0.0–1.0)
     *
     * Cara baca $pdRates:
     *   $pdRates[2] = 0.12  → akun dengan quality_grade_id=2 memiliki PD 12%
     *   (probability berpindah ke kualitas lebih buruk atau WO dalam 12 bulan)
     *
     * Prasyarat: MigrationMatrixBuilder sudah menghasilkan matriks untuk setiap cohort
     * dan PdMigrationCalculator sudah merata-ratakan PD lintas cohort.
     *
     * @param  array<int, float>  $pdRates  Key = quality_grade_id, value = pd_rate
     */
    public function writePdMigrationResult(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        string $dataStart,
        string $dataEnd,
        int $cohortCount,
        array $pdRates,
        ?string $notes = null,
    ): void {
        foreach ($pdRates as $qualityGradeId => $pdRate) {
            PdMigrationResult::create([
                'calculation_run_log_id' => $runLog->id,
                'usage_type' => $usageType->value,
                'from_quality_grade_id' => $qualityGradeId,
                'calculation_period' => $calculationPeriod,
                'pd_rate' => $pdRate,
                'cohort_count' => $cohortCount,
                'data_period_start' => $dataStart,
                'data_period_end' => $dataEnd,
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Simpan detail matriks migrasi (per cohort, per grade asal→tujuan/WO) ke tabel snapshot.
     * Ref: PRD Bab 8, pd-migration.md Bab 2 — insert-only, satu baris per (cohort, from_grade, to_grade).
     *
     * Baris dihasilkan oleh MigrationMatrixBuilder::buildRows() untuk setiap cohort yang
     * di-resolve PdMigrationCalculator::getCohorts(). cohort_period = periode awal cohort.
     *
     * @param  array<int, array{from_quality_grade_id:int, to_quality_grade_id:?int, migration_rate:float, source_outstanding:float, destination_outstanding:float, cohort_period:string}>  $rows
     */
    public function writePdMigrationMatrix(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        array $rows,
    ): void {
        foreach ($rows as $row) {
            PdMigrationMatrix::create([
                'calculation_run_log_id' => $runLog->id,
                'usage_type' => $usageType->value,
                'from_quality_grade_id' => $row['from_quality_grade_id'],
                'to_quality_grade_id' => $row['to_quality_grade_id'],
                'cohort_period' => $row['cohort_period'],
                'migration_rate' => $row['migration_rate'],
                'source_outstanding' => $row['source_outstanding'],
                'destination_outstanding' => $row['destination_outstanding'],
            ]);
        }
    }

    /**
     * Simpan hasil PD Netflow ke 4 tabel tersegmentasi: Konsolidasi, Modal Kerja, Investasi, Konsumsi.
     * Ref: PRD Bab 7 — insert-only, satu baris per bucket per periode.
     *
     * Konsolidasi menerima data dari semua segmen (agregasi weighted average dari job).
     * Tiga tabel segmen lain hanya menerima data bila usageType cocok.
     *
     * Dipanggil dari PdNetflowCalculationJob setelah writePdNetflowResult().
     *
     * @param  array<int, float>  $pdRates  Key = bucket_id, value = pd_rate
     * @param  array<int, float>  $transitionRates  Key = bucket_id, value = avg transition rate terakhir
     * @param  array<int, float>  $compoundRates  Key = bucket_id, value = compound rate akhir window
     * @param  array<int, float>  $sourceOs  Key = bucket_id, value = outstanding awal periode
     * @param  array<int, float>  $destOs  Key = bucket_id, value = outstanding akhir periode
     */
    public function writePdNetflowSegmented(
        CalculationRunLog $runLog,
        UsageType $usageType,
        string $calculationPeriod,
        string $dataStart,
        string $dataEnd,
        int $windowMonths,
        array $pdRates,
        array $transitionRates,
        array $compoundRates,
        array $sourceOs,
        array $destOs,
    ): void {
        // Tentukan model tabel per-segmen berdasarkan UsageType
        $segmentModel = match ($usageType) {
            UsageType::ModalKerja => PdNetflowModalKerja::class,
            UsageType::Investasi => PdNetflowInvestasi::class,
            UsageType::Konsumsi => PdNetflowKonsumsi::class,
        };

        foreach ($pdRates as $bucketId => $pdRate) {
            $row = [
                'calculation_run_log_id' => $runLog->id,
                'from_bucket_id' => $bucketId,
                'calculation_period' => $calculationPeriod,
                'pd_rate' => $pdRate,
                'data_period_start' => $dataStart,
                'data_period_end' => $dataEnd,
                'window_months' => $windowMonths,
                'transition_rate' => $transitionRates[$bucketId] ?? null,
                'compound_rate' => $compoundRates[$bucketId] ?? null,
                'source_outstanding' => $sourceOs[$bucketId] ?? null,
                'destination_outstanding' => $destOs[$bucketId] ?? null,
            ];

            // Simpan ke tabel segmen spesifik (Modal Kerja / Investasi / Konsumsi)
            $segmentModel::create($row);

            // Simpan ke tabel konsolidasi (semua segmen masuk)
            PdNetflowConsolidated::create($row);
        }
    }
}
