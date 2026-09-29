<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\SegmentType;
use App\Models\CalculationSegmentationLevel;
use App\Models\FinancingAccount;

/**
 * Resolver daftar kode akad (level 3 segmentasi bertingkat).
 *
 * Paralel dengan OfficeSegmentResolver (level 1 = kantor); di sini untuk level 3 = akad.
 * Akad diambil dari data pembiayaan aktual — hanya akad yang punya data pada periode/window tsb.
 *
 * Ref: PRD Bab 5 (segmentasi: kantor → jenis penggunaan → akad).
 */
final class AkadSegmentResolver
{
    /**
     * Apakah level segmentasi "Akad Code" aktif? (Ref: calculation_segmentation_levels)
     */
    public static function akadLevelActive(): bool
    {
        static $active = null;

        if ($active === null) {
            $active = CalculationSegmentationLevel::query()
                ->where('segment_type', SegmentType::AkadCode->value)
                ->where('is_active', true)
                ->exists();
        }

        return $active;
    }

    /**
     * Daftar akad_code yang memiliki data pembiayaan pada periode terkait.
     *
     * @param  string|null  $period  Format yyyymm. NULL = semua periode.
     * @param  string|null  $officeCode  Filter per kantor (null = semua kantor).
     * @param  int|null  $usageTypeValue  Filter per jenis penggunaan (null = semua).
     * @param  string[]|null  $eligibleAkadCodes  Whitelist dari AkadEligibilityService (null = semua akad).
     * @return string[]
     */
    public static function akadCodes(
        ?string $period = null,
        ?string $officeCode = null,
        ?int $usageTypeValue = null,
        ?array $eligibleAkadCodes = null,
    ): array {
        $query = FinancingAccount::query()
            ->when($officeCode !== null, fn ($q) => $q->where('office_code', $officeCode))
            ->when($usageTypeValue !== null, fn ($q) => $q->where('usage_type', $usageTypeValue))
            ->when($eligibleAkadCodes !== null, fn ($q) => $q->whereIn('akad_code', $eligibleAkadCodes));

        if ($period !== null) {
            $query->whereHas('accountPeriods', fn ($q) => $q->where('period', $period));
        }

        return $query
            ->distinct()
            ->orderBy('akad_code')
            ->pluck('akad_code')
            ->filter(fn ($code) => (string) $code !== '')
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();
    }

    /**
     * Daftar target akad untuk satu segmen: konsolidasi (null) + tiap kode akad.
     *
     * Bila level AkadCode tidak aktif → hanya [null] (konsolidasi semua akad).
     *
     * @param  string[]|null  $eligibleAkadCodes  Whitelist dari AkadEligibilityService
     * @return array<int, string|null> null = konsolidasi semua akad; string = kode akad spesifik
     */
    public static function runTargets(
        string $akadKey,
        int $usageTypeValue,
        ?string $period,
        ?string $officeCode = null,
    ): array {
        if (! self::akadLevelActive()) {
            return [null];
        }

        $eligibleAkadCodes = AkadEligibilityService::eligibleCodes($akadKey, $usageTypeValue);

        return array_merge(
            [null],
            self::akadCodes($period, $officeCode, $usageTypeValue, $eligibleAkadCodes),
        );
    }
}
