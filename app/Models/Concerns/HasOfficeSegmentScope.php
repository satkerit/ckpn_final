<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Scope segmentasi kantor (level 1) untuk tabel snapshot hasil kalkulasi.
 *
 * DESAIN:
 *   Tabel snapshot menyimpan dua jenis baris:
 *   - office_code NULL  → konsolidasi seluruh kantor (perilaku lama)
 *   - office_code '001' → hasil khusus satu kantor
 *
 *   Global scope di bawah membuat SEMUA query Eloquent default hanya membaca baris
 *   KONSOLIDASI, sehingga tampilan/laporan lama tidak berubah (tidak dobel baris).
 *   Untuk membaca pecahan kantor, gunakan scope officeCode() yang otomatis melepas
 *   global scope tersebut.
 *
 * Contoh:
 *   PdNetflowResult::where('usage_type', 1)->get();        // konsolidasi
 *   PdNetflowResult::officeCode('01')->where(...)->get();  // khusus kantor 01
 *
 * Ref: PRD Bab 5 (segmentasi bertingkat), Bab 15.
 */
trait HasOfficeSegmentScope
{
    public static function bootHasOfficeSegmentScope(): void
    {
        static::addGlobalScope('officeConsolidated', function (Builder $query): void {
            $table = $query->getModel()->getTable();
            $query->whereNull($table.'.office_code');
        });
    }

    /**
     * Batasi query ke satu kode kantor (melepas global scope konsolidasi).
     * $officeCode = null tetap berarti konsolidasi.
     */
    public function scopeOfficeCode(Builder $query, ?string $officeCode): Builder
    {
        if ($officeCode === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query
            ->withoutGlobalScope('officeConsolidated')
            ->where($table.'.office_code', $officeCode);
    }
}
