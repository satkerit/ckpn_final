<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AkadCalculationRule;
use Illuminate\Support\Facades\Cache;

/**
 * Repository untuk resolusi field perhitungan per akad.
 * Cache hasil untuk menghindari query berulang.
 * Ref: PRD Bab 5, 7, User requirement #5
 */
class AkadCalculationRulesRepository
{
    private const CACHE_KEY_PREFIX = 'akad_calc_rule_';

    private const CACHE_TTL = 3600; // 1 jam

    /**
     * Resolve field yang dipakai untuk perhitungan: outstanding_balance atau tgkmdl.
     * Default: outstanding_balance jika akad tidak ditemukan atau tidak aktif.
     */
    public function getCalculationField(string $akadCode): string
    {
        $cacheKey = self::CACHE_KEY_PREFIX.$akadCode;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($akadCode) {
            $rule = AkadCalculationRule::where('akad_code', $akadCode)
                ->where('is_active', true)
                ->first();

            return $rule?->use_field ?? 'outstanding_balance';
        });
    }

    /**
     * Clear cache untuk force reload dari DB.
     * Gunakan setelah update/insert rule baru.
     */
    public function clearCache(?string $akadCode = null): void
    {
        if ($akadCode) {
            Cache::forget(self::CACHE_KEY_PREFIX.$akadCode);
        } else {
            Cache::flush();
        }
    }
}
