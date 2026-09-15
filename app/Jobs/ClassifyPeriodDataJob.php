<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ClassificationType;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Models\CalculationParameter;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\CkpnPeriodClassification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Klasifikasi data staging di ckpn_period_classifications menjadi Individual atau Kolektif.
 * Posisi dalam alur sistem: LANGKAH PRE-1b (setelah PopulatePeriodDebtorsJob selesai).
 * Data staging sudah diisi oleh PopulatePeriodDebtorsJob sebelumnya.
 * Ref: PRD Bab 6.1, 12a Step 3
 *
 * ATURAN KLASIFIKASI:
 * Individual — semua kriteria di bawah harus terpenuhi:
 *   - financing_status = 'A' (aktif/stsrec)
 *   - writeoff_status != 'W' (bukan write-off)
 *   - collectibility >= npl_min_collectibility (dari parameter) ATAU masuk top-N outstanding
 *
 * Kolektif = semua akun yang tidak memenuhi kriteria Individual di atas,
 * termasuk akun dengan kualitas lancar, write-off, atau tidak masuk top-N.
 *
 * PARAMETER KONFIGURASI (dari tabel calculation_parameters, tanpa filter usage_type):
 *   - npl_min_collectibility: nilai collectibility minimum untuk masuk Individual
 *     (default: 3 = kurang lancar). Contoh: nilai 3 berarti collectibility 3, 4, 5 masuk NPL.
 *   - ckpn_individual_top_n_outstanding: jumlah akun terbesar (outstanding) yang
 *     otomatis masuk Individual meski kolektibilitasnya di bawah npl_min (default: 10).
 *
 * SUMBER DATA:
 *   Tabel ckpn_period_classifications (diisi PopulatePeriodDebtorsJob).
 *   Hasil klasifikasi ditulis kembali ke kolom classification ('individual' / 'collective')
 *   dan classification_reason di tabel yang sama.
 *
 * OUTPUT:
 *   Update batch (via DB::table chunked) ke tabel ckpn_period_classifications.
 *   run_log dicatat dengan ringkasan jumlah akun individual dan kolektif.
 */
class ClassifyPeriodDataJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $ckpnPeriodId,
        private readonly int $triggeredByUserId,
    ) {}

    /**
     * Eksekusi klasifikasi data staging dan update kolom classification di DB.
     *
     * Langkah eksekusi:
     * 1. Buat run_log baru (RunType::Classification) untuk audit trail proses ini.
     * 2. Baca parameter npl_min_collectibility dan ckpn_individual_top_n_outstanding
     *    dari calculation_parameters (keduanya tanpa filter usage_type — berlaku global).
     * 3. Muat semua baris staging ckpn_period_classifications untuk periode ini.
     * 4. Tentukan top-N akun berdasarkan outstanding terbesar (diurutkan desc, ambil N pertama).
     * 5. Iterasi setiap akun:
     *    - Jika financing_status='A' DAN writeoff_status!='W' DAN
     *      (collectibility >= npl_min_collectibility ATAU akun masuk top-N) → 'individual'
     *    - Selain itu → 'collective'
     * 6. Update batch ke DB menggunakan chunked DB::table insert/update (500 per batch)
     *    untuk menghindari query terlalu besar.
     *    Update run_log ke Completed dengan ringkasan jumlah. Jika gagal, update ke Failed.
     */
    public function handle(): void
    {
        $ckpnPeriod = CkpnPeriod::findOrFail($this->ckpnPeriodId);
        $period = $ckpnPeriod->period;

        $runLog = CalculationRunLog::create([
            'period' => $period,
            'run_type' => RunType::Classification,
            'usage_type' => null,
            'status' => RunStatus::Processing,
            'triggered_by_user_id' => $this->triggeredByUserId,
            'started_at' => now(),
        ]);

        try {
            // Ambil parameter klasifikasi dari tabel parameter — Ref: AGENTS.md §9
            $nplMinCollectibility = (int) (CalculationParameter::whereNull('usage_type')
                ->where('parameter_key', 'npl_min_collectibility')
                ->value('parameter_value') ?? 3);

            $topN = (int) (CalculationParameter::whereNull('usage_type')
                ->where('parameter_key', 'ckpn_individual_top_n_outstanding')
                ->value('parameter_value') ?? 10);

            // Ambil semua data staging periode ini
            $stagingData = CkpnPeriodClassification::where('period', $period)->get();

            // Tentukan top-N outstanding terbesar dari akun yang aktif, bukan writeoff, DAN NPL
            // Ref: PRD Bab 6.1 — Individual = aktif + bukan WO + NPL + masuk top-N outstanding
            $topNIds = array_flip(
                array_unique(
                    $stagingData
                        ->filter(fn ($a) => $a->financing_status?->value === 'A'
                            && ($a->writeoff_status === null || $a->writeoff_status?->value !== 'W')
                            && $a->collectibility >= $nplMinCollectibility)
                        ->sortByDesc('outstanding_balance')
                        ->take($topN)
                        ->pluck('financing_account_id')
                        ->toArray()
                )
            );

            $individualCount = 0;
            $collectiveCount = 0;

            // Update setiap baris dengan hasil klasifikasi
            foreach ($stagingData as $row) {
                $isAktif = $row->financing_status?->value === 'A';
                $isWriteoff = $row->writeoff_status?->value === 'W';
                $isTopN = isset($topNIds[$row->financing_account_id]);

                // Individual HANYA jika aktif + bukan writeoff + masuk top-N (yang sudah NPL)
                if ($isAktif && ! $isWriteoff && $isTopN) {
                    $classification = ClassificationType::Individual;
                    $reason = 'top_n_npl_outstanding';
                    $individualCount++;
                } else {
                    $classification = ClassificationType::Collective;
                    $reason = $isWriteoff ? 'writeoff' : 'aktif_lancar_atau_lainnya';
                    $collectiveCount++;
                }

                DB::table('ckpn_period_classifications')
                    ->where('id', $row->id)
                    ->update([
                        'classification' => $classification->value,
                        'is_classified' => true,
                        'classification_reason' => $reason,
                    ]);
            }

            // Tandai periode sudah diklasifikasi + naikkan status ke in_progress — Ref: PRD Bab 12a Step 3
            $ckpnPeriod->update([
                'is_classified' => true,
                'status' => 'in_progress',
                'notes' => sprintf(
                    "Dasar data Klasifikasi [periode %s]:\n"
                        ."- Sumber staging: ckpn_period_classifications (diisi PopulatePeriodDebtorsJob)\n"
                        ."- Filter staging: financing_status='A'; akad 03 hanya jika jatuh tempo; writeoff_status!='W'\n"
                        ."- Parameter: npl_min_collectibility=%d, top_n=%d\n"
                        ."- Individual = aktif & bukan WO & NPL & masuk top-N outstanding\n"
                        ."- Kolektif = sisa (termasuk lancar & write-off)\n"
                        .'- Hasil: %d individual, %d kolektif',
                    $period,
                    $nplMinCollectibility,
                    $topN,
                    $individualCount,
                    $collectiveCount,
                ),
            ]);

            $runLog->update([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
                'notes' => sprintf(
                    'Klasifikasi selesai: %d akun (%d individual, %d kolektif)',
                    $stagingData->count(),
                    $individualCount,
                    $collectiveCount,
                ),
            ]);
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
