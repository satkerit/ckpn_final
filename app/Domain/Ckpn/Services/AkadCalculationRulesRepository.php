<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Models\AkadCalculationRule;
use Illuminate\Support\Facades\Cache;

/**
 * Repository untuk resolve field perhitungan per akad.
 * Cache hasil untuk performa (TTL 1 jam).
 * Ref: PRD Bab 5, 7, 8, User requirement #5
 */
final class AkadCalculationRulesRepository
{
    public const CACHE_KEY_PREFIX = 'akad_calc_rules:';
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get field name untuk akad tertentu.
     * Return 'outstanding_balance' atau 'tgkmdl'.
     * Default = outstanding_balance jika tidak ada rule.
     */
    public function getFieldForAkad(string $akadCode): string
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $akadCode;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($akadCode): string {
            $rule = AkadCalculationRule::where('akad_code', $akadCode)
                ->where('is_active', true)
                ->first();

            return $rule?->use_field ?? 'outstanding_balance';
        });
    }

    /**
     * Resolve field untuk calculationPeriod, officeCode, akadCode.
     * Wrapper untuk getFieldForAkad() — gunakan akad_code langsung.
     * Ref: PRD Bab 5, 7 — Akad Segmentation Level 2
     *
     * @param  string  $calculationPeriod  Format yyyymm (tidak digunakan saat ini, reserved untuk future logic)
     * @param  string|null  $officeCode  Office code (tidak digunakan saat ini, reserved)
     * @param  string|null  $akadCode  Akad code untuk resolve field; null → default outstanding_balance
     * @return string 'outstanding_balance' atau 'tgkmdl'
     */
    public function resolveField(string $calculationPeriod, ?string $officeCode = null, ?string $akadCode = null): string
    {
        if ($akadCode === null) {
            return 'outstanding_balance';
        }

        return $this->getFieldForAkad($akadCode);
    }

    /**
     * Get field names untuk multiple akad sekaligus.
     * Return array[akad_code] = field_name
     */
    public function getFieldsForAkads(array $akadCodes): array
    {
        $result = [];
        $missingFromCache = [];

        // Check cache first
        foreach ($akadCodes as $akadCode) {
            $cacheKey = self::CACHE_KEY_PREFIX . $akadCode;
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $result[$akadCode] = $cached;
            } else {
                $missingFromCache[] = $akadCode;
            }
        }

        // Fetch missing from DB
        if ($missingFromCache !== []) {
            $rules = AkadCalculationRule::where('is_active', true)
                ->whereIn('akad_code', $missingFromCache)
                ->get()
                ->keyBy('akad_code');

            foreach ($missingFromCache as $akadCode) {
                $field = $rules[$akadCode]?->use_field ?? 'outstanding_balance';
                $result[$akadCode] = $field;
                Cache::put(self::CACHE_KEY_PREFIX . $akadCode, $field, self::CACHE_TTL_SECONDS);
            }
        }

        return $result;
    }

    /**
     * Get all active rules (for admin UI / seeding).
     */
    public function getAllActiveRules(): array
    {
        return AkadCalculationRule::where('is_active', true)->get()->toArray();
    }

    /**
     * Clear cache untuk akad tertentu (atau semua jika null).
     */
    public function clearCache(?string $akadCode = null): void
    {
        if ($akadCode === null) {
            // Clear all - need to use tags or pattern
            Cache::flush(); // Aggressive but simple; consider tag-based in production
        } else {
            Cache::forget(self::CACHE_KEY_PREFIX . $akadCode);
        }
    }

    /**
     * Validate akad code exists in rules (optional check).
     */
    public function hasRule(string $akadCode): bool
    {
        return AkadCalculationRule::where('is_active', true)->where('akad_code', $akadCode)->exists();
    }
}