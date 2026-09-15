<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use App\Enums\FinancingStatus;
use Illuminate\Database\Query\Builder;

/**
 * Filter baseline data historis khusus PD Netflow.
 *
 * Ketentuan bisnis:
 * - stsrec = 'A' ATAU stsacc = 'W' (write-off ikut dihitung).
 * - POKPBY/kode akad '03': hanya diambil jika SUDAH jatuh tempo pada periode
 *   tersebut; jika belum jatuh tempo maka debitur tidak masuk baseline.
 *
 * WAJIB dipakai pada setiap query PD Netflow yang join
 * financing_account_periods (alias fap) dengan financing_accounts (alias fa).
 */
final class PdNetflowBaseline
{
    /**
     * Menerapkan filter baseline standar PD Netflow pada query yang sudah menggunakan alias tabel fap dan fa.
     *
     * Filter ini WAJIB diterapkan pada setiap query yang mengambil data akun untuk perhitungan PD Netflow
     * agar populasi akun konsisten dengan definisi baseline yang disepakati di PRD.
     *
     * Aturan bisnis yang diterapkan:
     * 1. Hanya akun dengan financing_status = 'A' (Aktif) ATAU writeoff pada bulan periode tersebut
     *    (writeoff_status='W' DAN DATE_FORMAT(writeoff_date,'%Y%m') = period) — bukan WO kumulatif.
     * 2. Akad '03' (POKPBY) hanya diikutkan jika maturity_date sudah terisi DAN
     *    maturity_date <= LAST_DAY(periode) — akad musyarakah yang belum jatuh tempo dikeluarkan.
     * 3. Jika $eligibleAkadCodes diberikan (tidak NULL), hanya akad dalam daftar tersebut yang masuk.
     *
     * Prasyarat: query HARUS sudah melakukan join/from dengan alias:
     * - `fap` → tabel `financing_account_periods`
     * - `fa`  → tabel `financing_accounts`
     *
     * @param  string[]|null  $eligibleAkadCodes  daftar akad dari parameter (NULL = semua akad)
     *                                            Ref: PRD Bab 7
     */
    public static function apply(Builder $query, ?array $eligibleAkadCodes = null): Builder
    {
        return $query
            ->when($eligibleAkadCodes !== null, fn ($q) => $q->whereIn('fa.akad_code', $eligibleAkadCodes))
            ->where(function ($q): void {
                $q->whereNull('fa.product_code')
                    ->orWhere('fa.product_code', '!=', '72');
            })
            ->where(function ($query): void {
                $query->where('fap.financing_status', FinancingStatus::Aktif->value)
                    ->orWhere(function ($q): void {
                        // WO hanya masuk jika writeoff_date jatuh di bulan periode tsb.
                        // Ref: PRD Bab 7 — writeoff per-periode, bukan kumulatif sepanjang masa.
                        $q->where('fap.writeoff_status', 'W')
                            ->whereRaw(
                                "DATE_FORMAT(fap.writeoff_date, '%Y%m') = fap.period"
                            );
                    });
            })
            ->where(function ($query): void {
                $query->where('fa.akad_code', '!=', '03')
                    ->orWhere(function ($maturityQuery): void {
                        $maturityQuery->where('fa.akad_code', '03')
                            ->whereNotNull('fap.maturity_date')
                            ->whereRaw("fap.maturity_date <= LAST_DAY(STR_TO_DATE(CONCAT(fap.period, '01'), '%Y%m%d'))");
                    });
            });
    }
}
