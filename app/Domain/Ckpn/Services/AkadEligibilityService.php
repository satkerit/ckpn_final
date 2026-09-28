<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Models\CalculationColumnConfig;

/**
 * Resolusi daftar kode akad (POKPBY) yang eligible sebagai dasar perhitungan CKPN / rate PD / rate LGD.
 *
 * Sumber: tabel calculation_column_configs (Ref: PRD Bab 15 + modul Parameter Kalkulasi baru).
 * - Daftar diambil dari kolom pokpby_code yang aktif pada method terkait.
 * - Daftar kosong = semua akad eligible (tanpa filter daftar).
 * - Aturan khusus akad '03' (POKPBY): hanya boleh digunakan jika sudah jatuh tempo —
 *   diterapkan oleh masing-masing engine via excludeNotYetMaturedAkad03() karena batas
 *   perbandingannya berbeda (LAST_DAY periode untuk PD/CKPN, writeoff_date untuk LGD-ER).
 */
final class AkadEligibilityService
{
    /** Method konfigurasi kolom untuk dasar perhitungan CKPN. */
    public const KEY_CKPN = 'ckpn';

    /** Method konfigurasi kolom untuk dasar perhitungan rate PD (Netflow & Migration). */
    public const KEY_PD_RATE = 'pd';

    /** Method konfigurasi kolom untuk dasar perhitungan rate LGD (ER & CS). */
    public const KEY_LGD_RATE = 'lgd';

    /**
     * Mengambil daftar kode akad (POKPBY) yang eligible dari `calculation_column_configs`.
     *
     * $paramKey    : KEY_CKPN, KEY_PD_RATE, atau KEY_LGD_RATE sesuai konteks engine.
     * $usageTypeValue : tidak dipakai — segmentasi kini ditangani CalculationDataRange.
     *
     * Daftar = seluruh pokpby_code aktif pada method tsb. Tanpa baris aktif = semua akad eligible.
     *
     * Akad '03' (POKPBY): walaupun termasuk dalam daftar, aturan maturity date tetap
     * diterapkan secara terpisah oleh masing-masing engine (bukan di sini).
     *
     * @return string[]|null NULL = semua akad eligible (tanpa filter daftar).
     */
    public static function eligibleCodes(string $paramKey, ?int $usageTypeValue = null): ?array
    {
        $codes = CalculationColumnConfig::query()
            ->where('method', $paramKey)
            ->where('is_active', true)
            ->pluck('pokpby_code')
            ->map(fn ($c) => trim((string) $c))
            ->filter(fn ($c) => $c !== '')
            ->unique()
            ->values()
            ->all();

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
