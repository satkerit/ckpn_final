<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolusi daftar kode akad yang eligible sebagai dasar perhitungan CKPN / rate PD / rate LGD.
 *
 * Sumber: tabel calculation_parameters (Ref: PRD Bab 15), format nilai CSV, mis. "01,02,04".
 * - Nilai kosong atau parameter tidak ada  = semua akad eligible (tanpa filter daftar).
 * - Aturan khusus akad '03' (POKPBY): hanya boleh digunakan jika sudah jatuh tempo —
 *   diterapkan oleh masing-masing engine via applyAkad03MaturityRule() karena batas
 *   perbandingannya berbeda (LAST_DAY periode untuk PD/CKPN, writeoff_date untuk LGD-ER).
 */
final class AkadEligibilityService
{
    /** Parameter: daftar akad dasar perhitungan CKPN. */
    public const KEY_CKPN = 'ckpn_eligible_akad_codes';

    /** Parameter: daftar akad dasar perhitungan rate PD (Netflow & Migration). */
    public const KEY_PD_RATE = 'pd_rate_akad_codes';

    /** Parameter: daftar akad dasar perhitungan rate LGD (ER & CS). */
    public const KEY_LGD_RATE = 'lgd_rate_akad_codes';

    /**
     * Mengambil daftar kode akad yang eligible untuk perhitungan tertentu dari tabel `calculation_parameters`.
     *
     * Kriteria penggunaan:
     * - $paramKey   : gunakan konstanta KEY_CKPN, KEY_PD_RATE, atau KEY_LGD_RATE sesuai konteks engine.
     * - $usageTypeValue : nilai enum UsageType (int) untuk filter per segmen; NULL = ambil parameter global.
     *
     * Logika prioritas parameter:
     * - Jika ada baris dengan usage_type spesifik → gunakan itu (menang atas global).
     * - Jika hanya ada baris usage_type NULL → gunakan sebagai fallback global.
     * - Jika parameter tidak ada atau nilainya kosong → return NULL (artinya SEMUA akad eligible, tanpa filter).
     *
     * Format nilai di tabel: CSV, mis. "01,02,04" → akan di-parse jadi ['01','02','04'].
     * Akad '03' (POKPBY): walaupun termasuk dalam daftar, aturan maturity date tetap
     * diterapkan secara terpisah oleh masing-masing engine (bukan di sini).
     *
     * @return string[]|null NULL = semua akad eligible (tanpa filter daftar).
     */
    public static function eligibleCodes(string $paramKey, ?int $usageTypeValue = null): ?array
    {
        $raw = DB::table('calculation_parameters')
            ->where('parameter_key', $paramKey)
            ->when(
                $usageTypeValue !== null,
                fn ($q) => $q->where(
                    fn ($w) => $w->where('usage_type', $usageTypeValue)->orWhereNull('usage_type')
                )
            )
            ->orderByRaw('usage_type IS NULL ASC') // spesifik dulu, global sebagai fallback
            ->value('parameter_value');

        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $codes = collect(preg_split('/[,\s;]+/', trim((string) $raw)) ?: [])
            ->map(fn ($c) => trim($c))
            ->filter(fn ($c) => $c !== '')
            ->unique()
            ->values()
            ->all();

        // Query builder raw dipakai di sini karena pembacaan parameter tunggal yang ringan.

        return $codes === [] ? null : $codes;
    }

    /**
     * Format daftar akad eligible untuk ditampilkan di catatan dasar data.
     * NULL = semua akad tanpa filter.
     */
    public static function formatCodes(?array $codes): string
    {
        return $codes === null ? 'SEMUA (tanpa filter akad)' : implode(', ', $codes);
    }

    /**
     * Terapkan filter WHERE IN daftar akad pada query.
     * $column harus qualified sesuai konteks alias query pemanggil (mis. 'fa.akad_code'
     * untuk join query builder, 'financing_accounts.akad_code' untuk whereHas Eloquent).
     * Tidak melakukan apa-apa jika $codes NULL (semua akad).
     */
    public static function restrict($query, ?array $codes, string $column): mixed
    {
        if ($codes === null) {
            return $query;
        }

        return $query->whereIn($column, $codes);
    }

    /**
     * Mengecualikan akun akad '03' (POKPBY) yang belum jatuh tempo dari query Eloquent.
     *
     * Aturan bisnis: akad '03' hanya boleh diikutkan dalam perhitungan jika maturity_date
     * sudah terisi DAN sudah <= batas tanggal yang ditentukan engine.
     *
     * Kriteria parameter:
     * - $query         : Eloquent query builder yang sedang dibangun (di-mutate langsung).
     * - $akadSubQuery  : closure untuk whereHas ke relasi financingAccount guna mengecek akad_code = '03'.
     * - $boundSql      : ekspresi SQL batas jatuh tempo yang sudah disiapkan engine pemanggil.
     *                    Contoh PD/CKPN : "LAST_DAY(STR_TO_DATE(CONCAT(period,'01'),'%Y%m%d'))"
     *                    Contoh LGD-ER  : kolom writeoff_date
     *
     * Logika: exclude( akad='03' AND (maturity_date IS NULL OR maturity_date > batas) )
     * sehingga akad '03' yang sudah jatuh tempo tetap masuk, yang belum jatuh tempo dibuang.
     *
     * @param  string  $akadSubQuery  closure whereHas ke relasi financingAccount untuk cek akad
     * @param  string  $boundSql  SQL batas jatuh tempo (kolom polos tabel utama)
     */
    public static function excludeNotYetMaturedAkad03($query, callable $akadSubQuery, string $boundSql): void
    {
        $query->whereNot(function ($q) use ($akadSubQuery, $boundSql): void {
            $q->where($akadSubQuery)
                ->where(function ($m) use ($boundSql): void {
                    $m->whereNull('maturity_date')->orWhereRaw("maturity_date > $boundSql");
                });
        });
    }
}
