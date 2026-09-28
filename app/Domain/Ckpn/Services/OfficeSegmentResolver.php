<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Enums\SegmentType;
use App\Models\CalculationSegmentationLevel;
use App\Models\FinancingAccount;

use function array_merge;

/**
 * Resolver daftar kode kantor (level 1 segmentasi bertingkat).
 *
 * Kode kantor diambil langsung dari data pembiayaan (financing_accounts) untuk
 * periode yang dihitung, sehingga daftar selalu mengikuti data aktual — kantor
 * tanpa data pembiayaan tidak ikut dihitung (tidak menghasilkan snapshot kosong).
 *
 * DIPAKAI OLEH: semua job kalkulasi (PD Netflow/Migration, LGD ER/CS/Final,
 * CKPN) untuk memecah hasil per kantor + konsolidasi.
 *
 * Ref: PRD Bab 5 (segmentasi: kantor → jenis penggunaan → akad).
 */
final class OfficeSegmentResolver
{
    /**
     * Ambil daftar kode kantor yang memiliki data pembiayaan pada periode (atau window) terkait.
     * Bila $akadCodes diberikan, hanya kantor yang punya akun dengan akad eligible.
     *
     * @param  string|null  $period  Format yyyymm. NULL = semua periode (dipakai LGD-ER window).
     * @param  string[]|null  $akadCodes  Filter akad eligible (null = semua akad).
     * @return string[] Daftar kode kantor unik, terurut.
     */
    public static function officeCodes(?string $period = null, ?array $akadCodes = null): array
    {
        $query = FinancingAccount::query()
            ->when($akadCodes !== null, fn ($q) => $q->whereIn('akad_code', $akadCodes));

        if ($period !== null) {
            $query->whereHas('accountPeriods', fn ($q) => $q->where('period', $period));
        }

        return $query
            ->distinct()
            ->orderBy('office_code')
            ->pluck('office_code')
            ->filter(fn ($code) => (string) $code !== '')
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();
    }

    /**
     * Daftar target perhitungan untuk satu segmen: konsolidasi (null) + tiap kode kantor.
     *
     * Dipakai dispatcher (UI) untuk membuat satu CalculationRunLog per target, sehingga
     * status/retry tiap kantor terpisah dan audit trail tetap utuh — Ref: AGENTS.md §4.
     *
     * @param  string  $akadKey  KEY_PD_RATE / KEY_LGD_RATE / KEY_CKPN sesuai engine
     * @param  int  $usageType  Nilai enum UsageType
     * @param  string|null  $period  Periode yyyymm (null = semua periode)
     * @return array<int, string|null> null = konsolidasi, string = kode kantor
     */
    public static function runTargets(string $akadKey, int $usageType, ?string $period): array
    {
        // Konsolidasi selalu dihitung; pecahan per kantor mengikuti konfigurasi
        // level segmentasi 1 (Parameter Kalkulasi → Segmentasi).
        if (! self::officeLevelActive()) {
            return [null];
        }

        $akadCodes = AkadEligibilityService::eligibleCodes($akadKey, $usageType);

        return array_merge([null], self::officeCodes($period, $akadCodes));
    }

    /**
     * Apakah level segmentasi "Kode Kantor" aktif? (Ref: calculation_segmentation_levels)
     */
    public static function officeLevelActive(): bool
    {
        static $active = null;

        if ($active === null) {
            $active = CalculationSegmentationLevel::query()
                ->where('segment_type', SegmentType::OfficeCode->value)
                ->where('is_active', true)
                ->exists();
        }

        return $active;
    }
}
