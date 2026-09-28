<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Collective;

use App\Domain\Ckpn\Services\PokpbyCriteriaService;
use App\Enums\ClassificationType;
use App\Enums\UsageType;
use App\Models\CkpnPeriodClassification;
use App\Models\FinancingAccountPeriod;
use App\Models\LgdFinalResult;
use App\Models\PdMigrationResult;
use App\Models\PdNetflowResult;

/**
 * Calculates CKPN Collective per account: CKPN = PD x LGD x EAD.
 * Ref: PRD Bab 11
 *
 * PD selection: 'netflow' | 'migration' (per kebijakan segmen, dikonfigurasi via parameter)
 * LGD: diambil dari snapshot lgd_final_result (gabungan ER + CS) per segmen — Ref: PRD Bab 11
 * EAD = outstanding balance periode perhitungan
 *
 * Ref PRD Bab 12: kombinasi PD/LGD belum final — sistem dibangun fleksibel.
 * TODO(PRD Bab 12.3): konfirmasi skema kombinasi PD akhir (primary method per segmen vs weighted avg)
 */
final class CkpnCollectiveCalculator
{
    public function __construct(
        /** 'netflow' | 'migration' — PD method yang dipakai untuk segmen ini */
        private readonly string $pdMethod,
    ) {}

    /**
     * Hitung CKPN Kolektif per akun untuk satu segmen dan periode.
     * Ref: PRD Bab 11
     *
     * Formula per akun:
     *   CKPN = PD × LGD × EAD
     *   EAD  = nilai berdasarkan POKPBY (outstanding_balance atau tgkmdl)
     *   PD   = pd_rate dari snapshot (netflow atau migration, tergantung $pdMethod)
     *   LGD  = lgd_final_rate dari snapshot lgd_final_results per segmen
     *
     * Kriteria akun yang diproses:
     *   - Masuk klasifikasi Collective di tabel ckpn_period_classifications untuk periode ini
     *   - Segmen (usage_type) sesuai parameter
     *   - Memenuhi kriteria POKPBY jika termasuk dalam daftar khusus (parameter `pokpby_special_criteria_list`)
     *     Contoh: POKPBY=10 harus sudah jatuh tempo.
     *   - Snapshot LGD Final harus sudah tersedia (lihat LgdFinalCalculator)
     *
     * Pemilihan PD rate per akun:
     *   - PD netflow  : dipilih berdasarkan bucket_id yang dipetakan dari collectibility akun
     *   - PD migration: dipilih berdasarkan quality_grade_id yang dipetakan dari collectibility akun
     *   - Jika tidak ada mapping yang cocok → gunakan rata-rata semua PD rates segmen (fallback)
     *
     * Prasyarat (harus dikerjakan sebelum job ini):
     *   1. ClassifyAccounts — mengisi ckpn_period_classifications
     *   2. CalculatePdNetflow atau CalculatePdMigration — mengisi snapshot PD
     *   3. CalculateLgdFinal — mengisi snapshot lgd_final_results
     *
     * TODO(PRD Bab 12.3): konfirmasi skema kombinasi PD akhir (primary method per segmen vs weighted avg)
     *
     * @param  string  $calculationPeriod  Format yyyymm, mis. 202412
     * @param  string|null  $officeCode  Kode kantor (level 1 segmentasi); NULL = semua kantor — Ref: PRD Bab 5
     * @return array<int, array{
     *   financing_account_id: int,
     *   usage_type: string,
     *   pd_method_used: string,
     *   pd_rate: float,
     *   lgd_method_used: string,
     *   lgd_rate: float,
     *   ead: float,
     *   pokpby_code: int,
     *   tgkmdl_balance: float|null,
     *   outstanding_balance: float,
     *   ckpn_amount: float,
     *   office_code: string|null,
     * }>
     */
    public function calculatePerAccount(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null): array
    {
        // FIX: Ambil data langsung dari ckpn_period_classifications agar konsisten dengan rekonsiliasi
        // Sumber data harus SAMA dengan yang digunakan saat klasifikasi — Ref: PRD Bab 11
        $stagingAccounts = CkpnPeriodClassification::where('period', $calculationPeriod)
            ->where('classification', ClassificationType::Collective->value)
            ->whereHas('financingAccount', function ($q) use ($usageType, $officeCode) {
                $q->where('usage_type', $usageType->value)
                    // Segmentasi level 1: pecahan per kode kantor — Ref: PRD Bab 5
                    ->when($officeCode !== null, fn ($w) => $w->where('office_code', $officeCode));
            })
            ->with('financingAccount')
            ->get();

        if ($stagingAccounts->isEmpty()) {
            return [];
        }

        // Resolve PD rates untuk segmen (per bucket/kualitas, diambil dari snapshot terbaru).
        // Kantor spesifik → pakai PD pecahan kantor tsb, fallback konsolidasi jika belum ada.
        $pdRates = $this->resolvePdRates($usageType, $calculationPeriod, $officeCode);

        // Resolve LGD Final rate dari snapshot lgd_final_result (gabungan ER + CS) per segmen
        $lgdRate = $this->resolveLgdFinalRate($usageType, $calculationPeriod, $officeCode);
        $lgdMethod = 'lgd_final';

        $results = [];

        foreach ($stagingAccounts as $staging) {
            $account = $staging->financingAccount;
            $pokpbyCode = (int) $account->akad_code;
            $financingAccountId = $account->id;

            // Cek apakah akun memenuhi kriteria POKPBY
            if (! PokpbyCriteriaService::meetsCriteria($pokpbyCode, $financingAccountId, $calculationPeriod)) {
                // Skip akun yang tidak memenuhi kriteria (misal: POKPBY=10 belum jatuh tempo)
                continue;
            }

            // Get nilai outstanding dan tgkmdl untuk periode ini
            $periodData = FinancingAccountPeriod::where('financing_account_id', $financingAccountId)
                ->where('period', $calculationPeriod)
                ->first();

            $outstanding = (float) $staging->outstanding_balance;
            $tgkmdl = $periodData?->tgkmdl !== null ? (float) $periodData->tgkmdl : null;

            // Get EAD berdasarkan POKPBY
            $ead = PokpbyCriteriaService::getEadValue($pokpbyCode, $outstanding, $tgkmdl);

            $collectibility = (int) $staging->collectibility;

            // PD selection: gunakan bucket/quality-grade dari collectibility mapping
            // Fallback ke PD rata-rata segmen jika tidak ada mapping langsung
            [$pdRate, $bucketIdUsed, $qualityGradeIdUsed] = $this->selectPdRate($pdRates, $collectibility);

            // Formula: CKPN = PD x LGD x EAD
            $ckpnAmount = $pdRate * $lgdRate * $ead;

            $results[] = [
                'financing_account_id' => $financingAccountId,
                'usage_type' => $usageType->value,
                // Stamp kantor asal akun (dipakai SnapshotWriter untuk kolom office_code)
                'office_code' => $account->office_code !== null ? (string) $account->office_code : null,
                'pd_method_used' => $this->pdMethod,
                'pd_rate' => $pdRate,
                'lgd_method_used' => $lgdMethod,
                'lgd_rate' => $lgdRate,
                'ead' => $ead,
                'pokpby_code' => $pokpbyCode,
                'tgkmdl_balance' => $tgkmdl,
                'outstanding_balance' => $outstanding,
                // Bucket (netflow) atau quality grade (migration) yang dipakai untuk memilih PD rate
                'pd_bucket_id' => $bucketIdUsed,
                'pd_quality_grade_id' => $qualityGradeIdUsed,
                'ckpn_amount' => $ckpnAmount,
            ];
        }

        return $results;
    }

    /**
     * Muat PD rates dari snapshot terbaru untuk satu segmen dan periode.
     * Ref: PRD Bab 7 (Netflow) / Bab 8 (Migration)
     *
     * Berdasarkan $pdMethod yang dikonfigurasi saat konstruksi:
     *   - 'netflow'   → ambil dari pd_netflow_results, key = from_bucket_id (1–14)
     *   - 'migration' → ambil dari pd_migration_results, key = from_quality_grade_id (1–5)
     *
     * Hasil digunakan oleh calculatePerAccount() untuk memetakan collectibility akun
     * ke PD rate yang paling relevan via selectPdRate().
     *
     * @param  string  $calculationPeriod  Format yyyymm
     * @return array<int, float> key = bucket_id (netflow) atau quality_grade_id (migration), value = pd_rate
     */
    private function resolvePdRates(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null): array
    {
        if ($this->pdMethod === 'netflow') {
            // Pecahan kantor (melepas global scope konsolidasi)
            $rows = PdNetflowResult::officeCode($officeCode)
                ->where('usage_type', $usageType->value)
                ->where('calculation_period', $calculationPeriod)
                ->pluck('pd_rate', 'from_bucket_id');

            // Fallback konsolidasi (office_code NULL) untuk kantor yang belum punya pecahan
            if ($rows->isEmpty() && $officeCode !== null) {
                $rows = PdNetflowResult::where('usage_type', $usageType->value)
                    ->where('calculation_period', $calculationPeriod)
                    ->pluck('pd_rate', 'from_bucket_id');
            }

            return $rows->map(fn ($v) => (float) $v)->all();
        }

        // migration
        $rows = PdMigrationResult::officeCode($officeCode)
            ->where('usage_type', $usageType->value)
            ->where('calculation_period', $calculationPeriod)
            ->pluck('pd_rate', 'from_quality_grade_id');

        if ($rows->isEmpty() && $officeCode !== null) {
            $rows = PdMigrationResult::where('usage_type', $usageType->value)
                ->where('calculation_period', $calculationPeriod)
                ->pluck('pd_rate', 'from_quality_grade_id');
        }

        return $rows->map(fn ($v) => (float) $v)->all();
    }

    /**
     * Ambil LGD Final rate dari snapshot lgd_final_results untuk satu segmen.
     * Ref: PRD Bab 11
     *
     * Rate ini merupakan gabungan LGD ER + LGD CS yang sudah dihitung oleh LgdFinalCalculator.
     * Satu nilai rate berlaku untuk seluruh akun dalam segmen yang sama di periode yang sama.
     *
     * Prasyarat: job CalculateLgdFinal harus sudah selesai untuk periode ini.
     *
     * @param  string  $calculationPeriod  Format yyyymm
     * @return float LGD Final rate (0.0–1.0)
     *
     * @throws \RuntimeException jika snapshot tidak ditemukan.
     */
    private function resolveLgdFinalRate(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null): float
    {
        // Pecahan kantor (melepas global scope konsolidasi)
        $rate = LgdFinalResult::officeCode($officeCode)
            ->where('usage_type', $usageType->value)
            ->where('calculation_period', $calculationPeriod)
            ->value('lgd_final_rate');

        // Fallback konsolidasi (office_code NULL) untuk kantor yang belum punya pecahan
        if ($rate === null && $officeCode !== null) {
            $rate = LgdFinalResult::where('usage_type', $usageType->value)
                ->where('calculation_period', $calculationPeriod)
                ->value('lgd_final_rate');
        }

        if ($rate === null) {
            throw new \RuntimeException(
                "Snapshot LGD Final untuk segmen {$usageType->label()} periode {$calculationPeriod} belum tersedia. "
                    .'Jalankan perhitungan LGD Final terlebih dahulu.',
            );
        }

        return (float) $rate;
    }

    /**
     * Pilih PD rate yang paling relevan berdasarkan collectibility akun.
     * Ref: PRD Bab 7 (Netflow) / Bab 8 (Migration)
     *
     * Pemetaan collectibility ke key PD rates:
     *   - Netflow  : key = bucket_id, collectibility dipakai langsung sebagai lookup key
     *   - Migration: key = quality_grade_id, collectibility dipakai langsung sebagai lookup key
     *
     * Jika collectibility tidak memiliki mapping langsung di $pdRates:
     *   → fallback: gunakan rata-rata semua PD rates yang tersedia untuk segmen itu
     *   → bucket_id / quality_grade_id dikembalikan null (tidak ada kecocokan spesifik)
     *
     * Jika $pdRates kosong (snapshot belum ada): return [0.0, null, null]
     *
     * @param  array<int, float>  $pdRates  Map dari bucket_id/quality_grade_id ke pd_rate
     * @param  int  $collectibility  Kolektibilitas akun (1–5)
     * @return array{float, int|null, int|null} [pd_rate, bucket_id_used, quality_grade_id_used]
     */
    private function selectPdRate(array $pdRates, int $collectibility): array
    {
        if (empty($pdRates)) {
            return [0.0, null, null];
        }

        if (isset($pdRates[$collectibility])) {
            // Netflow: collectibility dipetakan ke bucket_id; Migration: ke quality_grade_id
            $bucketId = $this->pdMethod === 'netflow' ? $collectibility : null;
            $qualityGradeId = $this->pdMethod === 'migration' ? $collectibility : null;

            return [$pdRates[$collectibility], $bucketId, $qualityGradeId];
        }

        // Fallback: rata-rata semua bucket/grade — tidak ada bucket/grade spesifik yang cocok
        return [array_sum($pdRates) / count($pdRates), null, null];
    }
}
