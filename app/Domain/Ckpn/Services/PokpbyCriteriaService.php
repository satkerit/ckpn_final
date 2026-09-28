<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\ParameterMethod;
use App\Models\CalculationColumnConfig;
use App\Models\FinancingAccountPeriod;
use Illuminate\Support\Carbon;

/**
 * Service untuk menangani kriteria POKPBY (Kode Jenis Akad) dalam perhitungan CKPN/EAD.
 *
 * Konfigurasi dibaca dari tabel terstruktur `calculation_column_configs`
 * (method=ead): kolom EAD per POKPBY + flag wajib jatuh tempo.
 *
 * Ref: Permintaan customer - POKPBY='10' harus sudah jatuh tempo dan memakai tgkmdl.
 */
class PokpbyCriteriaService
{
    /** Field EAD fallback jika POKPBY tidak punya konfigurasi kolom aktif. */
    private const DEFAULT_EAD_FIELD = 'outstanding_balance';

    /** @var array<int>|null Daftar POKPBY dengan kriteria khusus (cached) */
    private static ?array $specialCriteriaList = null;

    /**
     * Get daftar POKPBY yang memerlukan kriteria khusus — yaitu yang punya
     * konfigurasi kolom EAD aktif dengan flag jatuh tempo atau kolom non-default.
     *
     * @return array<int>
     */
    public static function getSpecialCriteriaPokpbyList(): array
    {
        if (self::$specialCriteriaList === null) {
            self::$specialCriteriaList = CalculationColumnConfig::query()
                ->where('method', ParameterMethod::Ead->value)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('require_maturity', true)
                    ->orWhere('column_name', '!=', self::DEFAULT_EAD_FIELD))
                ->pluck('pokpby_code')
                ->map(fn ($code) => (int) $code)
                ->all();
        }

        return self::$specialCriteriaList;
    }

    /**
     * Cek apakah POKPBY memerlukan kriteria khusus.
     */
    public static function isSpecialCriteriaPokpby(int $pokpbyCode): bool
    {
        return in_array($pokpbyCode, self::getSpecialCriteriaPokpbyList(), true);
    }

    /**
     * Cek apakah POKPBY harus sudah jatuh tempo sebelum dihitung.
     */
    public static function requiresMaturity(int $pokpbyCode): bool
    {
        return CalculationColumnConfig::requiresMaturity((string) $pokpbyCode);
    }

    /**
     * Get field yang digunakan untuk EAD berdasarkan POKPBY.
     *
     * @return string nama kolom pada financing_account_periods
     */
    public static function getEadFieldForPokpby(int $pokpbyCode): string
    {
        return CalculationColumnConfig::columnFor(
            ParameterMethod::Ead,
            (string) $pokpbyCode,
            self::DEFAULT_EAD_FIELD
        );
    }

    /**
     * Get field default untuk EAD jika POKPBY tidak ada dalam mapping.
     */
    public static function getDefaultEadField(): string
    {
        return self::DEFAULT_EAD_FIELD;
    }

    /**
     * Cek apakah akun sudah jatuh tempo pada periode tertentu.
     */
    public static function isMatured(int $financingAccountId, string $period): bool
    {
        $maturityDate = FinancingAccountPeriod::where('financing_account_id', $financingAccountId)
            ->where('period', $period)
            ->value('maturity_date');

        if (! $maturityDate) {
            // Tidak ada data maturity date, anggap sudah jatuh tempo
            return true;
        }

        try {
            $periodDate = Carbon::createFromFormat('Ymd', $period.'01');
            $maturity = Carbon::createFromFormat('Y-m-d', $maturityDate);

            return $maturity->lte($periodDate);
        } catch (\Throwable) {
            // Format tanggal tidak valid, anggap sudah jatuh tempo
            return true;
        }
    }

    /**
     * Cek apakah akun memenuhi kriteria untuk dihitung berdasarkan POKPBY-nya.
     *
     * @return bool True jika akun memenuhi kriteria untuk dihitung
     */
    public static function meetsCriteria(int $pokpbyCode, int $financingAccountId, string $period): bool
    {
        // Jika bukan POKPBY khusus, langsung eligible
        if (! self::isSpecialCriteriaPokpby($pokpbyCode)) {
            return true;
        }

        // Cek apakah harus sudah jatuh tempo
        if (self::requiresMaturity($pokpbyCode)) {
            return self::isMatured($financingAccountId, $period);
        }

        return true;
    }

    /**
     * Get nilai EAD berdasarkan POKPBY dan data akun.
     */
    public static function getEadValue(int $pokpbyCode, float $outstandingBalance, ?float $tgkmdl = null): float
    {
        $field = self::getEadFieldForPokpby($pokpbyCode);

        if ($field === 'tgkmdl' && $tgkmdl !== null) {
            return $tgkmdl;
        }

        // Fallback ke outstanding_balance jika:
        // 1. field = 'outstanding_balance'
        // 2. field = 'tgkmdl' tapi $tgkmdl null
        return $outstandingBalance;
    }
}
