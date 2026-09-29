<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use App\Domain\Ckpn\Pd\Contracts\PdCalculationMethodInterface;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\PeriodHelper;
use App\Domain\Ckpn\Services\RollingWindowResolver;
use App\Enums\UsageType;
use App\Models\Bucket;

/**
 * Calculates PD using the Netflow method.
 * Ref: PRD Bab 7
 */
final class PdNetflowCalculator implements PdCalculationMethodInterface
{
    public function __construct(
        private readonly RollingWindowResolver $windowResolver,
        private readonly BucketMovementValidator $validator,
    ) {}

    /**
     * Menjalankan seluruh perhitungan PD Netflow untuk satu segmen dan satu periode.
     *
     * Alur kalkulasi:
     * 1. Resolve $akadCodes dari parameter kalkulasi sesuai segmen ($usageType).
     * 2. Validasi data quality bucket movement (flag anomali ke tabel data_quality_anomalies).
     * 3. Muat data outstanding per bucket per periode dari financing_account_periods.
     * 4. Hitung transition rate antar bucket per periode (dari data historis aktual).
     * 5. Proyeksikan transition rate ke depan menggunakan rata-rata historis.
     * 6. Hitung compound flow loss diagonal per bucket (PD akhir per bucket).
     *
     * Segmentasi level 1: bila $officeCode diisi, populasi dibatasi akun kantor tsb
     * (Ref: PRD Bab 5); NULL = konsolidasi semua kantor.
     *
     * Ref: PRD Bab 7
     *
     * @return array{
     *   pd_rates: array<int, float>,
     *   transition_rates: array<int, array<string, float>>,
     *   compound_rates: array<int, array<string, float>>,
     *   outstanding_map: array<string, array<int, float>>,
     *   proj_periods: string[],
     *   rate_start: string,
     *   compound_end: string,
     *   history: array{row_count: int, periods: string[]},
     * }
     */
    public function calculate(UsageType $usageType, string $calculationPeriod, ?string $officeCode = null, ?string $akadCode = null): array
    {
        // Daftar akad eligible dari parameter (kosong = semua akad) — Ref: parameter pd_rate_akad_codes
        // usageType->value diteruskan agar resolusi akad mempertimbangkan segmentasi yang dikonfigurasi.
        $eligibleAkadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $usageType->value);

        // Jika job dijalankan per akad spesifik (level 3 segmentasi), filter hanya akad tsb.
        // Bila akadCode null → hitung konsolidasi semua akad eligible (whitelist dari eligibleAkadCodes).
        $akadCodes = ($akadCode !== null)
            ? [$akadCode]
            : $eligibleAkadCodes;

        // Resolve all period ranges from window
        $outstandingStart = $this->windowResolver->outstandingStartPeriod($calculationPeriod);
        $rateStart = $this->windowResolver->rateStartPeriod($calculationPeriod);
        $projStart = $this->windowResolver->projectionStartPeriod($calculationPeriod);
        $projLookbackStart = $this->windowResolver->projectionLookbackStart($calculationPeriod);
        $compoundEnd = $this->windowResolver->compoundFlowEndPeriod($calculationPeriod);
        $outstandingEnd = $this->windowResolver->outstandingEndPeriod($calculationPeriod);

        // All periods needed for outstanding data (outstandingStart to outstandingEnd)
        $outstandingPeriods = PeriodHelper::range($outstandingStart, $outstandingEnd);

        // Step 1: Load & agregasi outstanding per bucket per periode dari financing_account_periods
        // dengan filter akad/kantor yang sama. Agregasi dilakukan di SQL (OutstandingMapLoader)
        // agar tidak memuat baris mentah ke memori — OOM di worker pada dataset besar.
        $loaded = OutstandingMapLoader::load($usageType->value, $outstandingPeriods, $akadCodes, $officeCode);
        $outstandingMap = $loaded['map'];

        // Step 2: Validate data quality (Ref: PRD Bab 7.3) memakai outstanding map yang sama
        $this->validator->validate($usageType, $outstandingPeriods, $akadCodes, $officeCode, $outstandingMap);

        // Step 3: Load all buckets (B1–B13 for transition, B14 is target/default)
        $buckets = Bucket::orderBy('bucket_order')->get();
        $sourceBuckets = $buckets->filter(fn (Bucket $b) => $b->bucket_order < 14); // B1–B13

        // Peta order → id agar lookup bucket berikutnya O(1), bukan firstWhere() di loop.
        $bucketIdByOrder = [];
        foreach ($buckets as $b) {
            $bucketIdByOrder[$b->bucket_order] = $b->id;
        }

        // Step 4: Calculate transition rates for all actual periods (rateStart to calculationPeriod)
        $actualRateEnd = $calculationPeriod;
        $actualPeriods = PeriodHelper::range($rateStart, $actualRateEnd);

        // transitionRates[bucket_id][period] = rate
        $transitionRates = [];
        foreach ($sourceBuckets as $bucket) {
            $transitionRates[$bucket->id] = [];
            foreach ($actualPeriods as $period) {
                $prevPeriod = PeriodHelper::shiftBack($period, 1);
                $srcOut = $outstandingMap[$prevPeriod][$bucket->id] ?? 0.0;
                $nextBucketId = $bucketIdByOrder[$bucket->bucket_order + 1] ?? null;
                $dstOut = $nextBucketId !== null ? ($outstandingMap[$period][$nextBucketId] ?? 0.0) : 0.0;

                $transitionRates[$bucket->id][$period] = ($srcOut > 0)
                    ? min(1.0, $dstOut / $srcOut)
                    : 0.0;
            }
        }

        // Step 5: Calculate projected rates for projStart to compoundEnd.
        //
        // Bug 3 fix: periode proyeksi harus mencakup sampai compoundEnd — bukan hanya sampai
        // calculationPeriod + forwardProjectionMonths — karena compound diagonal untuk bucket
        // paling awal (B1) membutuhkan chain rate sejauh (13 langkah) ke depan dari setiap
        // startPeriod. Jika startPeriod = rateStart, chain membutuhkan rate sampai
        // rateStart + 13 bulan. Dengan memperpanjang projPeriods sampai cukup jauh
        // (rateStart + maxBucketDepth + forwardProjectionMonths), semua rate tersedia.
        $maxBucketDepth = $sourceBuckets->count(); // jumlah langkah chain terpanjang (B1 → B14 = 13 langkah)
        $projEnd = PeriodHelper::shiftForward(
            $rateStart,
            $maxBucketDepth + $this->windowResolver->forwardProjectionMonths()
        );

        $projPeriods = PeriodHelper::range($projStart, $projEnd);

        $actualRateEndForLookback = PeriodHelper::shiftBack($projStart, 1); // = calculationPeriod
        foreach ($sourceBuckets as $bucket) {
            $lookbackPeriods = PeriodHelper::range($projLookbackStart, $actualRateEndForLookback);
            $lookbackRates = array_map(
                fn (string $p) => $transitionRates[$bucket->id][$p] ?? 0.0,
                $lookbackPeriods,
            );
            $avgRate = count($lookbackRates) > 0
                ? array_sum($lookbackRates) / count($lookbackRates)
                : 0.0;

            foreach ($projPeriods as $projPeriod) {
                $transitionRates[$bucket->id][$projPeriod] = $avgRate;
            }
        }

        // Step 6: Compute compound flow to loss per bucket per start_period.
        // compound(bucket_i, t) = rate(i→i+1, t) × rate(i+1→i+2, t+1) × ... × rate(13→14, t+n)
        // compoundPeriods = rateStart..compoundEnd (semua startPeriod yang ingin dievaluasi)
        $compoundPeriods = PeriodHelper::range($rateStart, $compoundEnd);
        $compoundRates = []; // [bucket_id][start_period] = compound_rate

        foreach ($sourceBuckets as $bucket) {
            $compoundRates[$bucket->id] = [];
            foreach ($compoundPeriods as $startPeriod) {
                $compound = 1.0;
                $curPeriod = $startPeriod;
                $curOrder = $bucket->bucket_order;

                while ($curOrder < 14) {
                    $curBucketId = $bucketIdByOrder[$curOrder] ?? null;
                    $nextBucketId = $bucketIdByOrder[$curOrder + 1] ?? null;
                    if ($curBucketId === null || $nextBucketId === null) {
                        break;
                    }
                    // Ambil rate dari transitionRates — tersedia karena projPeriods diperpanjang
                    $rate = $transitionRates[$curBucketId][$curPeriod] ?? 0.0;
                    $compound *= $rate;
                    $curOrder++;
                    $curPeriod = PeriodHelper::shiftForward($curPeriod, 1);
                }

                $compoundRates[$bucket->id][$startPeriod] = $compound;
            }
        }

        // Step 7: PD per bucket = average compound flow across all start periods
        $pdRates = [];
        foreach ($sourceBuckets as $bucket) {
            $values = array_values($compoundRates[$bucket->id] ?? []);
            $pdRates[$bucket->id] = count($values) > 0
                ? min(1.0, array_sum($values) / count($values))
                : 0.0;
        }

        // Rekam jejak audit ringkas — tidak lagi memuat baris mentah per akun (penyebab OOM).
        // Jumlah baris source dihitung agregat di SQL oleh OutstandingMapLoader.
        $history = [
            'row_count' => $loaded['row_count'],
            'periods' => $outstandingPeriods,
        ];

        // Step 8 (Phase 3+): Collect detail breakdown per office_code + akad_code + bucket + periode
        // untuk pivot display di UI. Load detail outstanding aggregate dari financing_account_periods,
        // lalu hitung transition & compound rates per detail breakdown.
        $detailOutstanding = OutstandingMapLoader::loadDetailed($usageType->value, $outstandingPeriods, $akadCodes, $officeCode);

        // Compute transition rates per location+akad (needed for compound rate calc)
        $detailTransitionRates = OutstandingMapLoader::computeDetailedTransitionRates(
            $detailOutstanding,
            $bucketIdByOrder,
        );

        // Compute compound rates per location+akad
        $compoundPeriods = PeriodHelper::range($rateStart, $compoundEnd);
        $detailCompoundRates = OutstandingMapLoader::computeDetailedCompoundRates(
            $detailTransitionRates,
            $bucketIdByOrder,
            $compoundPeriods,
        );

        // Merge outstanding + transition_rate + compound_rate ke satu struktur detail
        $detailBreakdown = $this->mergeDetailBreakdownWithRates(
            $detailOutstanding,
            $detailTransitionRates,
            $detailCompoundRates,
        );

        // Compute PD rates per akad breakdown dari detail compound rates
        $pdRatesPerAkad = $this->computePdRatesPerAkad($detailCompoundRates);

        return [
            'pd_rates' => $pdRates,
            'pd_rates_per_akad' => $pdRatesPerAkad,  // [office_code][akad_code][bucket_id] = pd_rate
            'transition_rates' => $transitionRates,
            'compound_rates' => $compoundRates,
            'outstanding_map' => $outstandingMap,
            'detail_breakdown' => $detailBreakdown,  // [office_code][akad_code][bucket_id][period] = {outstanding, transition_rate, compound_rate}
            'proj_periods' => $projPeriods,
            'rate_start' => $rateStart,
            'compound_end' => $compoundEnd,
            'history' => $history,
        ];
    }

    /**
     * Merge detail outstanding with transition & compound rates.
     *
     * @param  array<string, array<string, array<int, array<string, float>>>>  $detailOutstanding
     * @param  array<string, array<string, array<int, array<string, float>>>>  $detailTransitionRates
     * @param  array<string, array<string, array<int, array<string, float>>>>  $detailCompoundRates
     * @return array<string, array<string, array<int, array<string, array{outstanding: float, transition_rate?: float, compound_rate?: float}>>>>
     */
    private function mergeDetailBreakdownWithRates(
        array $detailOutstanding,
        array $detailTransitionRates,
        array $detailCompoundRates,
    ): array {
        $merged = [];

        foreach ($detailOutstanding as $officeCode => $byAkad) {
            foreach ($byAkad as $akadCode => $byBucket) {
                foreach ($byBucket as $bucketId => $byPeriod) {
                    foreach ($byPeriod as $period => $outstanding) {
                        $transRate = $detailTransitionRates[$officeCode][$akadCode][$bucketId][$period] ?? null;
                        $compRate = $detailCompoundRates[$officeCode][$akadCode][$bucketId][$period] ?? null;

                        if (! isset($merged[$officeCode])) {
                            $merged[$officeCode] = [];
                        }
                        if (! isset($merged[$officeCode][$akadCode])) {
                            $merged[$officeCode][$akadCode] = [];
                        }
                        if (! isset($merged[$officeCode][$akadCode][$bucketId])) {
                            $merged[$officeCode][$akadCode][$bucketId] = [];
                        }

                        $merged[$officeCode][$akadCode][$bucketId][$period] = [
                            'outstanding' => (float) $outstanding,
                            'transition_rate' => $transRate !== null ? (float) $transRate : null,
                            'compound_rate' => $compRate !== null ? (float) $compRate : null,
                        ];
                    }
                }
            }
        }

        return $merged;
    }

    /**
     * Compute PD rates per akad breakdown dari detail compound rates.
     *
     * PD rate per akad = rata-rata compound rate per akad (like aggregate PD calculation).
     *
     * @param  array<string, array<string, array<int, array<string, float>>>>  $detailCompoundRates
     * @return array<string, array<string, array<int, float>>> [office_code][akad_code][bucket_id] = pd_rate
     */
    private function computePdRatesPerAkad(array $detailCompoundRates): array
    {
        $pdRates = [];

        foreach ($detailCompoundRates as $officeCode => $byAkad) {
            foreach ($byAkad as $akadCode => $byBucket) {
                foreach ($byBucket as $bucketId => $byPeriod) {
                    $values = array_values($byPeriod ?? []);
                    $rate = count($values) > 0
                        ? min(1.0, array_sum($values) / count($values))
                        : 0.0;

                    if (! isset($pdRates[$officeCode])) {
                        $pdRates[$officeCode] = [];
                    }
                    if (! isset($pdRates[$officeCode][$akadCode])) {
                        $pdRates[$officeCode][$akadCode] = [];
                    }

                    $pdRates[$officeCode][$akadCode][$bucketId] = (float) $rate;
                }
            }
        }

        return $pdRates;
    }
}
