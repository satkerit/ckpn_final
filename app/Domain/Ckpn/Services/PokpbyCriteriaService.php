<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Models\CalculationParameter;
use App\Models\FinancingAccountPeriod;
use Illuminate\Support\Carbon;

/**
 * Service untuk menangani kriteria POKPBY (Kode Jenis Akad) dalam perhitungan CKPN/EAD.
 *
 * Parameter kustom yang digunakan:
 * - pokpby_special_criteria_list: daftar POKPBY yang memerlukan kriteria khusus (pisah koma)
 * - pokpby_require_maturity: mapping POKPBY ke requirement jatuh tempo (format: POKPBY=1 atau POKPBY=0)
 * - pokpby_ead_field: mapping POKPBY ke field EAD yang digunakan (format: POKPBY=field_name)
 * - default_ead_field: field default jika POKPBY tidak ada dalam mapping
 *
 * Ref: Permintaan customer - untuk POKPBY='10' harus sudah jatuh tempo
 *      dan yang digunakan adalah tgkmdl bukan osmdlc/outstanding pokok
 */
class PokpbyCriteriaService
{
    /** @var array<int> Daftar POKPBY yang memerlukan kriteria khusus (cached) */
    private static ?array $specialCriteriaList = null;

    /** @var array<string, int> Mapping POKPBY ke requirement jatuh tempo (cached) */
    private static ?array $requireMaturityMap = null;

    /** @var array<string, string> Mapping POKPBY ke field EAD yang digunakan (cached) */
    private static ?array $eadFieldMap = null;

    /** @var string Field default untuk EAD jika POKPBY tidak ada dalam mapping (cached) */
    private static ?string $defaultEadField = null;

    /**
     * Get daftar POKPBY yang memerlukan kriteria khusus.
     *
     * @return array<int> Array of POKPBY codes as integers
     */
    public static function getSpecialCriteriaPokpbyList(): array
    {
        if (self::$specialCriteriaList === null) {
            $value = CalculationParameter::where('parameter_key', 'pokpby_special_criteria_list')
                ->value('parameter_value') ?? '';

            $list = array_filter(
                array_map('trim', explode(',', $value)),
                fn ($code) => $code !== ''
            );

            self::$specialCriteriaList = array_map('intval', $list);
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
        if (self::$requireMaturityMap === null) {
            $value = CalculationParameter::where('parameter_key', 'pokpby_require_maturity')
                ->value('parameter_value') ?? '';

            $map = [];
            $pairs = array_filter(
                array_map('trim', explode(',', $value)),
                fn ($pair) => $pair !== ''
            );

            foreach ($pairs as $pair) {
                if (str_contains($pair, '=')) {
                    [$code, $flag] = explode('=', $pair, 2);
                    $map[trim($code)] = (int) trim($flag);
                }
            }

            self::$requireMaturityMap = $map;
        }

        return (bool) (self::$requireMaturityMap[(string) $pokpbyCode] ?? 0);
    }

    /**
     * Get field yang digunakan untuk EAD berdasarkan POKPBY.
     *
     * @return string 'tgkmdl' atau 'outstanding_balance'
     */
    public static function getEadFieldForPokpby(int $pokpbyCode): string
    {
        if (self::$eadFieldMap === null) {
            $value = CalculationParameter::where('parameter_key', 'pokpby_ead_field')
                ->value('parameter_value') ?? '';

            $map = [];
            $pairs = array_filter(
                array_map('trim', explode(',', $value)),
                fn ($pair) => $pair !== ''
            );

            foreach ($pairs as $pair) {
                if (str_contains($pair, '=')) {
                    [$code, $field] = explode('=', $pair, 2);
                    $map[trim($code)] = trim($field);
                }
            }

            self::$eadFieldMap = $map;
        }

        return self::$eadFieldMap[(string) $pokpbyCode] ?? self::getDefaultEadField();
    }

    /**
     * Get field default untuk EAD jika POKPBY tidak ada dalam mapping.
     */
    public static function getDefaultEadField(): string
    {
        if (self::$defaultEadField === null) {
            self::$defaultEadField = CalculationParameter::where('parameter_key', 'default_ead_field')
                ->value('parameter_value') ?? 'outstanding_balance';
        }

        return self::$defaultEadField;
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
