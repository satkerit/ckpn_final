<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Models\CalculationRunLog;
use App\Models\CkpnPeriod;
use App\Models\CkpnPeriodClassification;
use App\Models\FinancingAccountPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Populate daftar debitur ke staging tabel ckpn_period_classifications dari data historis.
 * Posisi dalam alur sistem: LANGKAH PRE-1a (step pertama sebelum klasifikasi).
 * Ref: PRD Bab 6.1, 12a Step 2
 *
 * TUJUAN:
 * Job ini memuat seluruh akun pembiayaan aktif periode yang dipilih ke tabel staging
 * ckpn_period_classifications, dengan classification = null (belum diklasifikasi).
 * Klasifikasi dilakukan terpisah oleh ClassifyPeriodDataJob (LANGKAH PRE-1b).
 *
 * FILTER AKUN YANG DIMUAT:
 *   - financing_status = 'A' (aktif/stsrec) — akun yang masih aktif per periode
 *   - writeoff_status != 'W' (bukan write-off/stsacc) — akun yang belum dihapusbukukan
 *   - Hanya akun yang akad-nya masih berlaku (tanggal maturity >= assessment date periode)
 *     Assessment date = akhir bulan dari periode (format yyyymm → last day of month)
 *
 * SUMBER DATA:
 *   - financing_account_periods: data historis pembiayaan per periode
 *   - financing_accounts: master data akun (untuk mendapatkan usage_type)
 *   Join dilakukan berdasarkan financing_account_id.
 *
 * OUTPUT:
 *   Insert batch (500 per chunk) ke tabel ckpn_period_classifications.
 *   Setiap baris: account_number, outstanding_balance, collectibility, financing_status,
 *   writeoff_status, usage_type, classification=null, classification_reason=null, ckpn_period_id.
 *   run_log dicatat dengan jumlah debitur yang dimuat.
 *
 * CATATAN:
 *   Job ini TIDAK melakukan klasifikasi — hanya populate data staging.
 *   Setelah selesai, ClassifyPeriodDataJob harus dijalankan untuk mengisi kolom classification.
 */
class PopulatePeriodDebtorsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        private readonly int $ckpnPeriodId,
        private readonly int $triggeredByUserId,
    ) {}

    /**
     * Eksekusi populate data staging debitur dan catat audit trail via run_log.
     *
     * Langkah eksekusi:
     * 1. Buat run_log baru (RunType::Classification) untuk audit trail.
     * 2. Query financing_account_periods dengan filter:
     *    - period = periode yang diminta
     *    - financing_status = 'A' (aktif)
     *    - writeoff_status IS NULL atau != 'W'
     *    - tanggal maturity akad >= assessment_date (akhir bulan periode)
     *    Join ke financing_accounts untuk mendapatkan usage_type.
     * 3. Map hasil query ke format insert: satu baris per akun dengan
     *    classification=null, classification_reason=null, ckpn_period_id.
     * 4. Insert batch 500 baris per chunk ke ckpn_period_classifications via DB::table.
     *    Update run_log ke Completed dengan jumlah debitur. Jika gagal, update ke Failed.
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
            // Ambil data historis: aktif (stsrec='A'), bukan write-off (stsacc<>'W'), dan bukan produk 72
            // Join financing_accounts untuk mendapatkan usage_type dan filter product_code — Ref: PRD Bab 6.1
            $accounts = FinancingAccountPeriod::where('financing_account_periods.period', $period)
                ->where('financing_account_periods.financing_status', 'A')
                ->where(fn ($q) => $q->whereNull('financing_account_periods.writeoff_status')
                    ->orWhere('financing_account_periods.writeoff_status', '!=', 'W'))
                ->join('financing_accounts', 'financing_accounts.id', '=', 'financing_account_periods.financing_account_id')
                ->where(function ($q): void {
                    $q->whereNull('financing_accounts.product_code')
                        ->orWhere('financing_accounts.product_code', '!=', '72');
                })
                ->where(function ($query) use ($period): void {
                    $assessmentDate = CarbonImmutable::createFromFormat('Ym', $period)->endOfMonth()->toDateString();

                    $query->where('financing_accounts.akad_code', '!=', '03')
                        ->orWhere(function ($maturityQuery) use ($assessmentDate): void {
                            $maturityQuery->where('financing_accounts.akad_code', '03')
                                ->whereNotNull('financing_account_periods.maturity_date')
                                ->whereDate('financing_account_periods.maturity_date', '<=', $assessmentDate);
                        });
                })
                ->select([
                    'financing_account_periods.financing_account_id',
                    'financing_account_periods.outstanding_balance',
                    'financing_account_periods.collectibility',
                    'financing_account_periods.financing_status',
                    'financing_account_periods.writeoff_status',
                    'financing_accounts.usage_type',
                ])
                ->get();

            // Idempotency: hapus data lama periode ini sebelum insert ulang
            CkpnPeriodClassification::where('period', $period)->delete();

            // Insert batch — classification null (staging, belum diklasifikasi)
            $rows = $accounts->map(fn (FinancingAccountPeriod $account): array => [
                'period' => $period,
                'financing_account_id' => $account->financing_account_id,
                'classification' => null,
                'is_classified' => false,
                'outstanding_balance' => $account->outstanding_balance,
                'collectibility' => $account->collectibility,
                'financing_status' => $account->financing_status?->value,
                'writeoff_status' => $account->writeoff_status?->value,
                'usage_type' => $account->usage_type,
                'classification_reason' => null,
                'ckpn_period_id' => $ckpnPeriod->id,
            ])->values()->toArray();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('ckpn_period_classifications')->insert($chunk);
            }

            $runLog->update([
                'status' => RunStatus::Completed,
                'completed_at' => now(),
                'notes' => sprintf('Populate selesai: %d debitur dimuat dari historis periode %s', count($rows), $period),
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
