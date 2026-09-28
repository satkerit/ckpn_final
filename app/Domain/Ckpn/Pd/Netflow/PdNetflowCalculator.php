<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use App\Domain\Ckpn\Pd\Contracts\PdCalculationMethodInterface;
use App\Domain\Ckpn\Services\AkadEligibilityService;
use App\Domain\Ckpn\Services\PeriodHelper;
use App\Domain\Ckpn\Services\RollingWindowResolver;
use App\Enums\UsageType;
use App\Models\Bucket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     *   history: array<int, array<string, mixed>>,
     * }
     */
    public function calculate(UsageType $usageType, string $calculationPeriod): array
    {
        // Daftar akad eligible dari parameter (kosong = semua akad) — Ref: parameter pd_rate_akad_codes
        // usageType->value diteruskan agar resolusi akad mempertimbangkan segmentasi yang dikonfigurasi.
        $akadCodes = AkadEligibilityService::eligibleCodes(AkadEligibilityService::KEY_PD_RATE, $usageType->value);

        // Resolve all period ranges from window
        $outstandingStart = $this->windowResolver->outstandingStartPeriod($calculationPeriod);
        $rateStart = $this->windowResolver->rateStartPeriod($calculationPeriod);
        $projStart = $this->windowResolver->projectionStartPeriod($calculationPeriod);
        $projLookbackStart = $this->windowResolver->projectionLookbackStart($calculationPeriod);
        $compoundEnd = $this->windowResolver->compoundFlowEndPeriod($calculationPeriod);
        $outstandingEnd = $this->windowResolver->outstandingEndPeriod($calculationPeriod);

        // All periods needed for outstanding data (outstandingStart to outstandingEnd)
        $outstandingPeriods = PeriodHelper::range($outstandingStart, $outstandingEnd);

        // Step 1: Validate data quality (Ref: PRD Bab 7.3)
        // Teruskan $akadCodes agar validator memeriksa populasi yang sama dengan kalkulator.
        $this->validator->validate($usageType, $outstandingPeriods, $akadCodes);

        // Step 2: Load outstanding data dari financing_account_periods dengan filter akad yang sama.
        // Ref: AGENTS.md §4 — query builder diizinkan untuk agregasi berat
        $outstandingMap = $this->loadOutstandingMap($usageType->value, $outstandingPeriods, $akadCodes);

        // Step 3: Load all buckets (B1–B13 for transition, B14 is target/default)
        $buckets = Bucket::orderBy('bucket_order')->get();
        $sourceBuckets = $buckets->filter(fn (Bucket $b) => $b->bucket_order < 14); // B1–B13

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
                $nextBucketId = $this->getNextBucketId($buckets, $bucket->bucket_order);
                $dstOut = $nextBucketId ? ($outstandingMap[$period][$nextBucketId] ?? 0.0) : 0.0;

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
                    $curBucket = $buckets->firstWhere('bucket_order', $curOrder);
                    $nextBucketId = $this->getNextBucketId($buckets, $curOrder);
                    if ($curBucket === null || $nextBucketId === null) {
                        break;
                    }
                    // Ambil rate dari transitionRates — tersedia karena projPeriods diperpanjang
                    $rate = $transitionRates[$curBucket->id][$curPeriod] ?? 0.0;
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

        $history = $this->loadCalculationHistory($usageType->value, $outstandingPeriods, $akadCodes);

        return [
            'pd_rates' => $pdRates,
            'transition_rates' => $transitionRates,
            'compound_rates' => $compoundRates,
            'outstanding_map' => $outstandingMap,
            'proj_periods' => $projPeriods,
            'rate_start' => $rateStart,
            'compound_end' => $compoundEnd,
            'history' => $history,
        ];
    }

    /**
     * Loads the exact historical rows used to build the PD Netflow outstanding map.
     *
     * @param  string[]  $periods
     * @param  string[]|null  $akadCodes
     * @return array<int, array<string, mixed>>
     */
    private function loadCalculationHistory(int $usageTypeValue, array $periods, ?array $akadCodes = null): array
    {
        return PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->where('fa.usage_type', $usageTypeValue)
                ->whereIn('fap.period', $periods),
            $akadCodes
        )
            ->select(
                'fa.account_number',
                'fa.customer_name',
                'fa.product_code',
                'fa.office_code',
                'fa.akad_code',
                'fap.maturity_date',
                'fap.period',
                'fap.outstanding_balance',
                'fap.tgkhari',
                'fap.collectibility',
                'fap.financing_status',
                'fap.writeoff_status',
            )
            ->orderBy('fap.period')
            ->orderBy('fa.account_number')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * Returns next bucket ID given current bucket order.
     */
    private function getNextBucketId(Collection $buckets, int $currentOrder): ?int
    {
        $next = $buckets->firstWhere('bucket_order', $currentOrder + 1);

        return $next?->id;
    }

    /**
     * Loads outstanding data as nested array [period][bucket_id] = total_outstanding.
     * Data source: financing_account_periods (per akun), di-aggregate per bucket berdasarkan tgkhari.
     * Filter akad $akadCodes diteruskan dari calculate() agar konsisten dengan segmentasi kalkulator.
     * Ref: AGENTS.md §4 — query builder diizinkan untuk agregasi berat
     *
     * @param  string[]  $periods
     * @param  string[]|null  $akadCodes  filter kode akad sesuai konfigurasi parameter kalkulasi
     * @return array<string, array<int, float>>
     */
    private function loadOutstandingMap(int $usageTypeValue, array $periods, ?array $akadCodes = null): array
    {
        $buckets = Bucket::orderBy('bucket_order')->get();

        // Query historical data dari financing_account_periods dengan filter akad dari parameter kalkulasi.
        $rows = PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->where('fa.usage_type', $usageTypeValue)
                ->whereIn('fap.period', $periods),
            $akadCodes
        )
            ->select('fap.period', 'fap.tgkhari', 'fap.outstanding_balance')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $period = $row->period;
            $tgkhari = (int) $row->tgkhari;
            $outstanding = (float) $row->outstanding_balance;

            $bucketId = $this->resolveBucketFromTgkhari($tgkhari, $buckets);

            if (! isset($map[$period][$bucketId])) {
                $map[$period][$bucketId] = 0.0;
            }
            $map[$period][$bucketId] += $outstanding;
        }

        return $map;
    }

    /**
     * Resolve bucket_id dari tgkhari (hari tunggakan).
     * Ref: PRD Bab 5 — bucketing berdasarkan rentang hari tunggakan per bucket
     */
    private function resolveBucketFromTgkhari(int $tgkhari, Collection $buckets): int
    {
        foreach ($buckets as $bucket) {
            if ($tgkhari >= $bucket->min_days_overdue && $tgkhari <= $bucket->max_days_overdue) {
                return $bucket->id;
            }
        }

        // Fallback: bucket terakhir (B14 — default untuk writeoff/lama)
        return $buckets->last()->id;
    }
}
