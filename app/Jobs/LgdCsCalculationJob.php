<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Lgd\CollateralShortfall\LgdCollateralShortfallCalculator;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\SnapshotWriter;
use App\Enums\RunStatus;
use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued job untuk menjalankan perhitungan LGD Collateral Shortfall (LGD-CS).
 * Posisi dalam alur sistem: LANGKAH 2b (paralel dengan LGD-ER, keduanya independen).
 * Ref: PRD Bab 10
 *
 * KONSEP LGD-CS:
 * LGD-CS mengukur kerugian berdasarkan kekurangan nilai agunan dibanding outstanding.
 * Digunakan untuk akun kolektif yang memiliki agunan aktif (collectibility=5 atau WO).
 *
 * Formula per akun:
 *   Collateral Net Value = estimated_sale_value (jika ada)
 *                          ATAU appraisal_value × (1 − discount_rate)
 *   Shortfall = max(0, outstanding − collateral_net_value)
 *   LGD Rate per akun = Shortfall / outstanding
 *   LGD Rate segmen = total_shortfall / total_outstanding (weighted average)
 *
 * KRITERIA ELIGIBILITAS AKUN:
 *   - collectibility = 5 (macet) ATAU writeoff_status = 'W' (write-off)
 *   - Wajib memiliki agunan aktif dengan nilai > 0
 *   - outstanding > 0
 *   - Akad 03 (Musyarakah) hanya diikutkan jika sudah JTP (Jatuh Tempo Pokok)
 *   - Kode akad lain sesuai daftar eligibel di AkadEligibilityService::KEY_LGD_RATE
 *
 * TIDAK ADA PARAMETER ROLLING WINDOW — LGD-CS menggunakan data snapshot periode saat ini,
 * bukan data historis. Ini berbeda dengan LGD-ER yang menggunakan rolling window.
 *
 * OUTPUT:
 *   Dua snapshot disimpan via SnapshotWriter:
 *   1. writeLgdCsResults()       → tabel lgd_collateral_shortfall_results (per akun)
 *   2. writeLgdCsBySegmentResult() → tabel lgd_cs_segment_results (agregat per segmen)
 *
 * IDEMPOTENCY:
 *   Job di-skip tanpa error jika run_log sudah berstatus Completed atau Approved.
 *   Max retry: 3x, timeout: 300 detik.
 */
class LgdCsCalculationJob implements ShouldQueue
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
     * Eksekusi perhitungan LGD Collateral Shortfall dan simpan dua snapshot hasilnya.
     *
     * Langkah eksekusi:
     * 1. Muat run_log dari DB; validasi idempotency (skip jika Completed/Approved).
     * 2. Bangun LgdCollateralShortfallCalculator (tidak ada parameter rolling window —
     *    perhitungan murni berbasis data periode calculationPeriod saat ini).
     * 3. Panggil calculatePerAccount() — menghasilkan array hasil per akun, masing-masing berisi:
     *    account_number, outstanding, collateral_net_value, shortfall, lgd_rate.
     * 4. Panggil aggregate() dari hasil per akun — menghasilkan ringkasan segmen:
     *    account_count, total_outstanding, total_shortfall, lgd_rate (weighted avg).
     * 5. Bangun catatan (notes) dengan ringkasan sumber data, filter eligibilitas, dan angka agregat.
     * 6. Tulis snapshot per akun ke lgd_collateral_shortfall_results
     *    via SnapshotWriter::writeLgdCsResults().
     * 7. Tulis snapshot agregat segmen ke lgd_cs_segment_results
     *    via SnapshotWriter::writeLgdCsBySegmentResult().
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
            $calculator = app(LgdCollateralShortfallCalculator::class);
            $writer = new SnapshotWriter;

            $accountResults = $calculator->calculatePerAccount($usageType, $this->calculationPeriod, $this->officeCode, $this->akadCode);
            $aggregate = $calculator->aggregate($accountResults);

            // Catatan dasar data perhitungan — Ref: instruksi user (notes per baris hasil)
            $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_LGD_RATE, $this->usageType);
            $notes = sprintf(
                "Dasar data LGD Collateral Shortfall [%s]:\n"
                    ."- Sumber: financing_account_periods (collectibility=5 ATAU writeoff_status='W') + collaterals (estimated_sale_value / appraisal_value*(1-discount))\n"
                    ."- Filter: akad 03 hanya jika JTP; HARUS punya agunan aktif ber-nilai; outstanding>0\n"
                    ."- Nilai jual bersih = estimated_sale_value (jika ada) ATAU appraisal_value*(1-discount)\n"
                    .'- account_count=%d; total_outstanding=%.2f; total_shortfall=%.2f',
                $usageType->label(),
                $aggregate['account_count'],
                $aggregate['total_outstanding'],
                $aggregate['total_shortfall'],
            );

            $writer->writeLgdCsResults(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                accountResults: $accountResults,
                notes: $notes,
                officeCode: $this->officeCode,
                akadCode: $this->akadCode,
            );

            $writer->writeLgdCsBySegmentResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                aggregate: $aggregate,
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
