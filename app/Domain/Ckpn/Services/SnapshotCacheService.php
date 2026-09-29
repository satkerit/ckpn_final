<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\UsageType;
use App\Models\PdNetflowResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\CkpnIndividualResult;
use Illuminate\Support\Facades\Cache;

class SnapshotCacheService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function getPdNetflowLatest(
        string $period,
        UsageType $usageType,
        ?string $officeCode = null,
    ): ?PdNetflowResult {
        $key = $this->cacheKey('pd_netflow', $period, $usageType->value, $officeCode);

        return Cache::remember($key, self::CACHE_TTL, function () use ($period, $usageType, $officeCode) {
            return PdNetflowResult::query()
                ->where('calculation_period', $period)
                ->where('usage_type', $usageType)
                ->when($officeCode, fn ($q) => $q->where('office_code', $officeCode))
                ->orderBy('created_at', 'desc')
                ->first();
        });
    }

    public function getLgdErLatest(
        string $period,
        UsageType $usageType,
        ?string $officeCode = null,
    ): ?LgdExpectedRecoveriesResult {
        $key = $this->cacheKey('lgd_er', $period, $usageType->value, $officeCode);

        return Cache::remember($key, self::CACHE_TTL, function () use ($period, $usageType, $officeCode) {
            return LgdExpectedRecoveriesResult::query()
                ->where('calculation_period', $period)
                ->where('usage_type', $usageType)
                ->when($officeCode, fn ($q) => $q->where('office_code', $officeCode))
                ->orderBy('created_at', 'desc')
                ->first();
        });
    }

    public function getCkpnIndividualBatch(
        string $period,
        UsageType $usageType,
        int $page = 1,
        int $perPage = 100,
    ) {
        $key = $this->cacheKey('ckpn_individual_batch', $period, $usageType->value, "page_{$page}_{$perPage}");

        return Cache::remember($key, self::CACHE_TTL, function () use ($period, $usageType, $page, $perPage) {
            return CkpnIndividualResult::query()
                ->where('calculation_period', $period)
                ->where('usage_type', $usageType)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        });
    }

    public function invalidateSnapshotCache(
        string $period,
        UsageType $usageType,
        ?string $officeCode = null,
    ): void {
        $keys = [
            $this->cacheKey('pd_netflow', $period, $usageType->value, $officeCode),
            $this->cacheKey('lgd_er', $period, $usageType->value, $officeCode),
            $this->cacheKey('ckpn_individual_batch', $period, $usageType->value, '*'),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    private function cacheKey(string $type, string $period, string $usageType, ?string $segment = null): string
    {
        $base = "ckpn_snapshot:{$type}:{$period}:{$usageType}";

        return $segment ? "{$base}:{$segment}" : $base;
    }
}
