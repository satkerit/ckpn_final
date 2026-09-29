<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Lgd\ExpectedRecoveries\LgdExpectedRecoveriesCalculator;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\PeriodHelper;
use App\Domain\Ckpn\Services\SnapshotWriter;
use App\Enums\CalculationMethodKey;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationDataRange;
use App\Models\CalculationRunLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued job untuk menjalankan perhitungan LGD Expected Recoveries (LGD-ER).
 * Posisi dalam alur sistem: LANGKAH 2a (setelah PD Netflow/Migration selesai).
 * Ref: PRD Bab 9
 *
 * KONSEP LGD-ER:
 * LGD-ER mengukur kerugian yang terjadi setelah akun di-write-off, berdasarkan
 * pemulihan (recovery) aktual dari debitur WO dalam rolling window historis.
 *
 * Formula:
 *   Recovery per akun = max(0, outstanding_writeoff − outstanding_perhitungan)
 *   Recovery Rate per akun = Recovery / outstanding_writeoff
 *   Expected Recovery Rate = avg(Recovery Rate) atas semua akun WO dalam window
 *   LGD Rate = 1 − Expected Recovery Rate
 *
 * PARAMETER KONFIGURASI (dari tabel calculation_parameters):
 *   - lgd_er_rolling_window_years: panjang window historis dalam tahun (default: 5).
 *     Window dihitung mundur dari calculationPeriod. Contoh: periode 202412, window 5 tahun
 *     → data dari 201912 s.d. 202412.
 *   - lgd_er_use_all_account: jika true, semua akun diikutkan tanpa filter segmen;
 *     jika false (default), hanya akun dengan usage_type yang sesuai.
 *   Priority parameter: usage_type-specific > all-account (usage_type IS NULL).
 *
 * POPULASI AKUN:
 *   Akun yang masuk perhitungan:
 *   - writeoff_status = 'W' (status write-off)
 *   - Masuk dalam periode rolling window
 *   - outstanding_writeoff > 0
 *
 * OUTPUT:
 *   Snapshot satu baris per kombinasi (usage_type, calculationPeriod) disimpan ke
 *   tabel lgd_expected_recoveries_results via SnapshotWriter::writeLgdErResult().
 *   Kolom utama: lgd_rate, expected_recovery_rate, total_writeoff, total_recovery,
 *   data_start, data_end, window_years, is_all_account.
 *
 * IDEMPOTENCY:
 *   Job di-skip tanpa error jika run_log sudah berstatus Completed atau Approved.
 *   Max retry: 3x, timeout: 300 detik.
 */
class LgdErCalculationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $runLogId,
        private readonly int $usageType,
        private readonly string $calculationPeriod,
        /** NULL = konsolidasi semua kantor; 'xxx' = pecahan per kode kantor (level 1 segmentasi) */
        private readonly ?string $officeCode = null,
        /** NULL = konsolidasi semua akad; 'xxx' = pecahan per kode akad (level 2 segmentasi) */
        private readonly ?string $akadCode = null,
    ) {}

    /**
     * Eksekusi perhitungan LGD Expected Recoveries dan simpan snapshot hasilnya.
     *
     * Langkah eksekusi:
     * 1. Muat run_log dari DB; validasi idempotency (skip jika Completed/Approved).
     * 2. Baca parameter dari calculation_parameters:
     *    - lgd_er_rolling_window_years (default 5): window data historis WO.
     *    - lgd_er_use_all_account (default false): apakah semua segmen digabung.
     *    Priority: parameter usage_type-specific lebih diutamakan dari all-account.
     * 3. Bangun LgdExpectedRecoveriesCalculator dengan kedua parameter tersebut.
     * 4. Panggil calculateWithDetails() — kalkulasi berlangsung di dalam kalkulator.
     *    Output $details berisi: lgd_rate, expected_recovery_rate, total_writeoff,
     *    total_recovery, is_all_account.
     * 5. Hitung dataStart = calculationPeriod dikurangi (windowYears × 12 bulan)
     *    menggunakan PeriodHelper::shiftBack(). Contoh: periode=202412, window=5 tahun
     *    → dataStart=201912.
     * 6. Bangun catatan (notes) berisi ringkasan dasar data: sumber, filter, hasil numerik.
     * 7. Tulis snapshot ke lgd_expected_recoveries_results via SnapshotWriter::writeLgdErResult().
     *    Update run_log ke Completed. Jika gagal, update ke Failed dan re-throw exception.
     */
    public function handle(): void
    {
        $runLog = CalculationRunLog::findOrFail($this->runLogId);
        $usageType = UsageType::from($this->usageType);

        // Idempotency guard — Ref: AGENTS.md §4, PRD Bab 16
        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $windowYears = (int) CalculationDataRange::resolveValue(
                CalculationMethodKey::LgdExpectedRecoveries,
                'lgd_er_rolling_window_years',
                officeCode: $this->officeCode,
                usageType: $this->usageType,
                akadCode: $this->akadCode,
                default: 5,
            );

            $useAllAccount = (bool) CalculationDataRange::resolveValue(
                CalculationMethodKey::LgdExpectedRecoveries,
                'lgd_er_use_all_account',
                officeCode: $this->officeCode,
                usageType: $this->usageType,
                akadCode: $this->akadCode,
                default: false,
            );

            $calculator = new LgdExpectedRecoveriesCalculator($windowYears, $useAllAccount);
            $writer = new SnapshotWriter;

            $details = $calculator->calculateWithDetails($usageType, $this->calculationPeriod, $this->officeCode, $this->akadCode);
            $dataStart = PeriodHelper::shiftBack($this->calculationPeriod, $windowYears * 12);

            // Catatan dasar data perhitungan — Ref: instruksi user (notes per baris hasil)
            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $this->usageType);
            $notes = sprintf(
                "Dasar data LGD Expected Recoveries [%s]:\n"
                    ."- Sumber: financing_account_periods (writeoff_status NOT NULL, writeoff_date terisi)\n"
                    ."- Filter: writeoff_date dlm window %d thn ke belakang dari periode; akad eligible: %s; akad 03 hanya jika maturity<=writeoff_date\n"
                    ."- Window: %d thn; periode writeoff: [%s, %s]\n"
                    ."- Fallback all-account: %s\n"
                    .'- total_writeoff=%.2f; total_recovery=%.2f',
                $usageType->label(),
                AkadEligibilityService::formatCodes($akadCodes),
                $windowYears,
                $windowYears,
                $dataStart,
                $this->calculationPeriod,
                $details['is_all_account'] ? 'YA (segmen kosong)' : 'TIDAK',
                $details['total_writeoff'],
                $details['total_recovery'],
            );

            $writer->writeLgdErResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                dataStart: $dataStart,
                dataEnd: $this->calculationPeriod,
                windowYears: $windowYears,
                lgdRate: $details['lgd_rate'],
                expectedRecoveryRate: $details['expected_recovery_rate'],
                totalWriteoff: $details['total_writeoff'],
                totalRecovery: $details['total_recovery'],
                isAllAccount: $details['is_all_account'],
                notes: $notes,
                officeCode: $this->officeCode,
                akadCode: $this->akadCode,
            );

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update(['status' => RunStatus::Failed, 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            throw $e;
        }
    }
}
