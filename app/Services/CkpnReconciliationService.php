<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi data klasifikasi CKPN vs hasil kalkulasi CKPN per periode.
 *
 * Tujuan: memastikan setiap akun yang diklasifikasikan sebagai Individual atau Collective
 * sudah memiliki baris hasil CKPN yang sesuai — tidak ada akun yang "hilang" (diklasifikasikan
 * tapi tidak dihitung) maupun "lebih" (terhitung tapi tidak ada di klasifikasi).
 *
 * Alur rekonsiliasi:
 *   1. Hitung jumlah akun & total EAD per (usage_type, classification) dari ckpn_period_classifications
 *   2. Hitung jumlah akun & total EAD hasil CKPN dari ckpn_individual_result & ckpn_collective_result
 *   3. Bandingkan keduanya — flag is_count_match & is_ead_match
 *   4. Upsert hasil ke ckpn_reconciliation_snapshots untuk audit trail
 *
 * Kriteria LULUS rekonsiliasi:
 *   - is_count_match = true  → jumlah baris klasifikasi == jumlah baris hasil CKPN
 *   - is_ead_match   = true  → total EAD klasifikasi == total EAD hasil (toleransi pembulatan desimal)
 *
 * Ref: PRD Bab 11
 */
class CkpnReconciliationService
{
    /**
     * Jalankan rekonsiliasi untuk periode tertentu dan simpan hasilnya ke snapshot.
     *
     * Prasyarat:
     *   - Job klasifikasi (ClassifyPeriodDataJob) sudah selesai → ckpn_period_classifications terisi
     *   - Job CKPN Individual & Collective sudah selesai → ckpn_individual_result & ckpn_collective_result terisi
     *
     * Proses:
     *   1. Agregasi klasifikasi per (usage_type, classification) → count + sum(outstanding_balance)
     *   2. Agregasi hasil Individual per usage_type (join financing_accounts untuk usage_type)
     *   3. Agregasi hasil Collective per usage_type
     *   4. Bandingkan count & EAD, set flag is_count_match / is_ead_match
     *   5. Upsert setiap baris ke ckpn_reconciliation_snapshots (unique: period+usage_type+classification)
     *
     * Output per baris rekonsiliasi (juga di-push ke Collection return):
     *   - period, usage_type, classification
     *   - count_classification / total_ead_classification  ← dari tabel klasifikasi
     *   - count_ckpn_result   / total_ead_ckpn_result      ← dari tabel hasil CKPN
     *   - is_count_match      ← true jika count sama
     *   - is_ead_match        ← true jika EAD sama (setelah pembulatan 2 desimal)
     *
     * @param  string  $period  Format yyyymm, mis. '202506'
     * @return Collection<int, object> Satu baris per (usage_type, classification)
     */
    public function runReconciliation(string $period): Collection
    {
        // Ambil agregat klasifikasi per (usage_type, classification)
        $classificationData = DB::table('ckpn_period_classifications')
            ->where('period', $period)
            ->select(
                'usage_type',
                'classification',
                DB::raw('COUNT(*) as count_rows'),
                DB::raw('SUM(outstanding_balance) as total_ead'),
            )
            ->groupBy('usage_type', 'classification')
            ->get()
            ->keyBy(fn ($row) => ($row->usage_type ?? '__null__').'|'.$row->classification);

        // Ambil agregat hasil CKPN individual per usage_type (join ke financing_accounts)
        $individualData = DB::table('ckpn_individual_result')
            ->join('financing_accounts', 'ckpn_individual_result.financing_account_id', '=', 'financing_accounts.id')
            ->where('ckpn_individual_result.calculation_period', $period)
            ->select(
                'financing_accounts.usage_type',
                DB::raw('COUNT(*) as count_rows'),
                DB::raw('SUM(ckpn_individual_result.outstanding_balance) as total_ead'),
            )
            ->groupBy('financing_accounts.usage_type')
            ->get()
            ->keyBy(fn ($row) => ($row->usage_type ?? '__null__').'|individual');

        // Ambil agregat hasil CKPN kolektif per usage_type
        $collectiveData = DB::table('ckpn_collective_result')
            ->where('calculation_period', $period)
            ->select(
                'usage_type',
                DB::raw('COUNT(*) as count_rows'),
                DB::raw('SUM(ead) as total_ead'),
            )
            ->groupBy('usage_type')
            ->get()
            ->keyBy(fn ($row) => ($row->usage_type ?? '__null__').'|collective');

        $ckpnData = $individualData->merge($collectiveData);

        // Gabungkan semua key dari classification maupun hasil CKPN
        $allKeys = $classificationData->keys()->merge($ckpnData->keys())->unique();

        $results = collect();

        foreach ($allKeys as $key) {
            [$usageTypeRaw, $classification] = explode('|', $key, 2);
            $usageType = $usageTypeRaw === '__null__' ? null : $usageTypeRaw;

            $clasRow = $classificationData->get($key);
            $ckpnRow = $ckpnData->get($key);

            $countClass = (int) ($clasRow->count_rows ?? 0);
            $eadClass = (float) ($clasRow->total_ead ?? 0);
            $countCkpn = (int) ($ckpnRow->count_rows ?? 0);
            $eadCkpn = (float) ($ckpnRow->total_ead ?? 0);
            $isCountMatch = $countClass === $countCkpn;
            $isEadMatch = abs($eadClass - $eadCkpn) < 0.01;

            DB::table('ckpn_reconciliation_snapshots')->upsert(
                [
                    'period' => $period,
                    'usage_type' => $usageType,
                    'classification' => $classification,
                    'count_classification' => $countClass,
                    'total_ead_classification' => $eadClass,
                    'count_ckpn_result' => $countCkpn,
                    'total_ead_ckpn_result' => $eadCkpn,
                    'is_count_match' => $isCountMatch,
                    'is_ead_match' => $isEadMatch,
                    'checked_at' => now(),
                ],
                ['period', 'usage_type', 'classification'],
                [
                    'count_classification',
                    'total_ead_classification',
                    'count_ckpn_result',
                    'total_ead_ckpn_result',
                    'is_count_match',
                    'is_ead_match',
                    'checked_at',
                ],
            );

            $results->push((object) [
                'period' => $period,
                'usage_type' => $usageType,
                'classification' => $classification,
                'count_classification' => $countClass,
                'total_ead_classification' => $eadClass,
                'count_ckpn_result' => $countCkpn,
                'total_ead_ckpn_result' => $eadCkpn,
                'is_count_match' => $isCountMatch,
                'is_ead_match' => $isEadMatch,
            ]);
        }

        return $results;
    }

    /**
     * Ambil snapshot rekonsiliasi tersimpan untuk periode tertentu.
     *
     * Berbeda dengan runReconciliation() yang menghitung ulang secara on-the-fly,
     * method ini hanya membaca hasil yang sudah disimpan ke ckpn_reconciliation_snapshots
     * (di-upsert oleh runReconciliation()). Gunakan ini untuk audit trail dan tampilan
     * histori rekonsiliasi tanpa harus menjalankan ulang kalkulasi.
     *
     * Urutan baris: classification ASC → usage_type ASC (konsisten untuk tampilan tabel UI).
     *
     * @param  string  $period  Format yyyymm, mis. '202506'
     * @return Collection<int, object> Kosong jika rekonsiliasi belum pernah dijalankan untuk periode ini
     */
    public function getSnapshot(string $period): Collection
    {
        return DB::table('ckpn_reconciliation_snapshots')
            ->where('period', $period)
            ->orderBy('classification')
            ->orderBy('usage_type')
            ->get();
    }
}
