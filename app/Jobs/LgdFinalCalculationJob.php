<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Ckpn\Lgd\LgdFinalCalculator;
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
 * Queued job untuk menghitung LGD Final per segmen (gabungan ER dan CS).
 * Posisi dalam alur sistem: LANGKAH 3 (setelah LGD-ER dan LGD-CS keduanya selesai).
 * Ref: PRD Bab 11
 *
 * KONSEP LGD FINAL:
 * LGD Final adalah LGD yang digunakan untuk input perhitungan CKPN Kolektif.
 * Nilainya dipilih atau digabungkan dari dua metode LGD yang tersedia:
 *   - LGD Expected Recoveries (LGD-ER): berbasis data pemulihan historis WO
 *   - LGD Collateral Shortfall (LGD-CS): berbasis kekurangan nilai agunan saat ini
 *
 * Pemilihan/penggabungan dilakukan oleh LgdFinalCalculator::calculateForSegment().
 * Logika penggabungan mengikuti konfigurasi yang disimpan di tabel calculation_parameters
 * (misal: max, min, weighted average, atau hanya salah satu metode).
 *
 * PRASYARAT:
 *   - Snapshot LGD-ER (lgd_expected_recoveries_results) untuk periode & usage_type
 *     yang sama harus sudah tersedia.
 *   - Snapshot LGD-CS (lgd_cs_segment_results) untuk periode & usage_type
 *     yang sama harus sudah tersedia.
 *   Jika salah satu belum ada, kalkulasi akan gagal dan run_log diset ke Failed.
 *
 * OUTPUT:
 *   Snapshot satu baris per kombinasi (usage_type, calculationPeriod) disimpan ke
 *   tabel lgd_final_results via SnapshotWriter::writeLgdFinalResult().
 *   Kolom utama: lgd_rate, lgd_er_rate, lgd_cs_rate, method_used.
 *
 * IDEMPOTENCY:
 *   Job di-skip tanpa error jika run_log sudah berstatus Completed atau Approved.
 *   run_log = null (periode dihapus) juga di-skip secara silent.
 *   Max retry: 3x, timeout: 300 detik.
 */
class LgdFinalCalculationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $runLogId,
        private readonly int $usageType,
        private readonly string $calculationPeriod,
    ) {}

    /**
     * Eksekusi penghitungan LGD Final dan simpan snapshot hasilnya.
     *
     * Langkah eksekusi:
     * 1. Muat run_log; jika null (periode dihapus) skip silent tanpa error.
     * 2. Validasi idempotency (skip jika Completed/Approved).
     * 3. Panggil LgdFinalCalculator::calculateForSegment() yang mengambil
     *    LGD-ER dan LGD-CS dari snapshot yang sudah ada, lalu menggabungkannya
     *    sesuai konfigurasi (max/min/weighted/single method).
     * 4. Tulis snapshot ke lgd_final_results via SnapshotWriter::writeLgdFinalResult().
     *    Kolom utama: lgd_rate, lgd_er_rate, lgd_cs_rate, method_used.
     *    Update run_log ke Completed. Jika gagal, update ke Failed dan re-throw exception.
     */
    public function handle(): void
    {
        $runLog = CalculationRunLog::find($this->runLogId);
        if ($runLog === null) {
            return; // Run log dihapus (periode dihapus), skip silently
        }
        $usageType = UsageType::from($this->usageType);

        // Idempotency guard — Ref: AGENTS.md §4, PRD Bab 16
        if ($runLog->status === RunStatus::Completed || $runLog->status === RunStatus::Approved) {
            return;
        }

        $runLog->update(['status' => RunStatus::Processing, 'started_at' => now()]);

        try {
            $calculator = new LgdFinalCalculator;
            $writer = new SnapshotWriter;

            $result = $calculator->calculateForSegment($usageType, $this->calculationPeriod);

            $writer->writeLgdFinalResult(
                runLog: $runLog,
                usageType: $usageType,
                calculationPeriod: $this->calculationPeriod,
                result: $result,
            );

            $runLog->update(['status' => RunStatus::Completed, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $runLog->update([
                'status' => RunStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }
}
