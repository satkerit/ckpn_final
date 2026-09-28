<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Ckpn\Pd\Netflow\PdNetflowBaseline;
use App\Domain\Ckpn\Services\PeriodHelper;
use App\Enums\CalculationMethodKey;
use App\Models\Bucket;
use App\Models\CalculationDataRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hitung PD Netflow Detail secara on-the-fly dari financing_account_periods untuk tampilan pivot UI.
 *
 * PD Netflow mengukur probabilitas penurunan kualitas pembiayaan antar bucket tunggakan
 * berdasarkan pergerakan outstanding selama rolling window:
 *   Transition Rate B_i → B_(i+1) pada periode P = outstanding[B_(i+1)][P] / outstanding[B_i][P-1]
 *   Compound Rate bucket B pada periode P = ∏ transition_rate dari awal window sampai P
 *   PD bucket B = rata-rata compound rate selama window (compound_avg)
 *
 * Contoh calculationPeriod = 202607, window = 36 bulan:
 *   outstanding_periods : 202307 s/d 202607 (37 titik data)
 *   transition_periods  : 202308 s/d 202607 (36 titik)
 *   compound_periods    : 202308 s/d 202607
 *   projection_periods  : 202608 s/d 202707 (12 bulan ke depan)
 *
 * Semua parameter (window, forward months, projection method, lookback) dibaca dari
 * tabel calculation_parameters dengan priority segmen > all-account.
 *
 * Ref: PRD Bab 7
 */
final class PdNetflowDetailService
{
    /**
     * Ambil panjang rolling window observasi dari calculation_parameters.
     *
     * Parameter key : 'pd_netflow_rolling_window_months'
     * Default       : 36 bulan (3 tahun)
     * Scope priority: segment-specific ($usageType != null) > all-account (null)
     *
     * Window ini menentukan berapa bulan ke belakang data outstanding diambil dari
     * financing_account_periods untuk menghitung transition rate dan compound rate.
     *
     * @param  string|null  $usageType  Nilai integer UsageType sebagai string; null = all-account
     * @return int Jumlah bulan window, minimum 1
     */
    private function windowMonths(?string $usageType): int
    {
        $value = CalculationDataRange::resolveValue(
            CalculationMethodKey::PdNetflow,
            'pd_netflow_rolling_window_months',
            usageType: $usageType !== null ? (int) $usageType : null,
            default: 36,
        );

        return (int) $value;
    }

    /**
     * Ambil jumlah bulan proyeksi ke depan dari calculation_data_ranges.
     *
     * Parameter key : 'pd_netflow_forward_projection_months'
     * Default       : 12 bulan (1 tahun ke depan dari calculationPeriod)
     * Scope priority: segmen paling spesifik > global
     *
     * Proyeksi menghitung transition rate ke depan untuk periode yang belum ada datanya,
     * menggunakan rata-rata lookback dari periode historis yang sudah ada.
     *
     * @param  string|null  $usageType  Nilai integer UsageType sebagai string; null = all-account
     * @return int Jumlah bulan proyeksi ke depan
     */
    private function forwardMonths(?string $usageType): int
    {
        $value = CalculationDataRange::resolveValue(
            CalculationMethodKey::PdNetflow,
            'pd_netflow_forward_projection_months',
            usageType: $usageType !== null ? (int) $usageType : null,
            default: 12,
        );

        return (int) $value;
    }

    /**
     * Ambil metode proyeksi transition rate dari calculation_data_ranges.
     *
     * Parameter key : 'pd_netflow_projection_method'
     * Nilai valid   : 'rolling' | 'full'
     * Default       : 'rolling'
     *
     * 'rolling' = gunakan rata-rata lookbackMonths terakhir sebelum titik proyeksi
     * 'full'    = gunakan rata-rata seluruh periode historis dalam window
     *
     * @param  string|null  $usageType  Nilai integer UsageType sebagai string; null = all-account
     * @return string 'rolling' atau 'full'
     */
    private function projectionMethod(?string $usageType): string
    {
        $value = CalculationDataRange::resolveValue(
            CalculationMethodKey::PdNetflow,
            'pd_netflow_projection_method',
            usageType: $usageType !== null ? (int) $usageType : null,
            default: 'rolling',
        );

        return (string) $value;
    }

    /**
     * Ambil jumlah bulan lookback untuk proyeksi rolling dari calculation_parameters.
     *
     * Parameter key : 'pd_netflow_projection_lookback_months'
     * Default       : sama dengan windowMonths() jika parameter tidak diset
     * Scope priority: segment-specific > all-account
     *
     * Lookback digunakan saat projectionMethod = 'rolling':
     *   projected_rate[B][P] = avg(transition_rate[B][P-lookback..P-1]) — rata-rata N bulan terakhir
     * Jika projectionMethod = 'full', lookback diabaikan (pakai seluruh window).
     *
     * @param  string|null  $usageType  Nilai integer UsageType sebagai string; null = all-account
     * @return int Jumlah bulan lookback
     */
    private function projectionLookbackMonths(?string $usageType): int
    {
        $value = CalculationDataRange::resolveValue(
            CalculationMethodKey::PdNetflow,
            'pd_netflow_projection_lookback_months',
            usageType: $usageType !== null ? (int) $usageType : null,
            default: null,
        );

        return $value !== null ? (int) $value : $this->windowMonths($usageType);
    }

    /**
     * Hitung seluruh data PD Netflow on-the-fly untuk satu periode kalkulasi dan opsional satu segmen.
     *
     * Output mencakup 5 lapisan data yang saling terkait:
     *
     *   1. outstanding[bucket_id][period]
     *      Total saldo pokok akun dalam bucket tersebut pada periode tsb.
     *      Sumber: SUM(outstanding_balance) dari financing_account_periods per bucket.
     *      Rentang: (calculationPeriod − window) s/d calculationPeriod  [window+1 titik]
     *
     *   2. transition[bucket_id][period]
     *      Transition rate B_i → B_(i+1) pada periode P:
     *        = outstanding[B_(i+1)][P] / outstanding[B_i][P-1]   (capped 0–1, 0 jika B_i[P-1]=0)
     *      Bucket terakhir (WO/B14) tidak punya transition (tidak ada bucket berikutnya).
     *      Rentang: (calculationPeriod − window + 1) s/d calculationPeriod  [window titik]
     *
     *   3. compound[bucket_id][period]
     *      Compound rate kumulatif = ∏ transition_rate dari awal window sampai periode P.
     *      Untuk periode proyeksi, compound melanjutkan dengan projected_rate.
     *      compound_avg[bucket_id] = rata-rata compound selama window = PD bucket tsb.
     *
     *   4. projection[bucket_id][projPeriod]
     *      Projected transition rate untuk periode setelah calculationPeriod.
     *      Method 'rolling'  → avg N (lookbackMonths) transition terakhir sebelum projPeriod
     *      Method 'full'     → avg seluruh transition dalam window historis
     *      Rentang proyeksi  : (calculationPeriod + 1) s/d (calculationPeriod + forwardMonths)
     *
     *   5. debtors (Collection)
     *      Raw data per akun dari financing_account_periods pada calculationPeriod,
     *      dilengkapi bucket_id hasil dari BucketingService. Digunakan untuk drill-down per akun.
     *
     * Parameter:
     *   $calculationPeriod — periode target format yyyymm
     *   $usageType         — null = all-account; string integer = satu segmen UsageType
     *
     * @param  string  $calculationPeriod  Format yyyymm
     * @param  string|null  $usageType  Integer UsageType sebagai string; null = all-account
     * @return array{
     *   outstanding_periods: string[],          ← window+1 periode outstanding
     *   transition_periods: string[],           ← window periode transition
     *   compound_periods: string[],             ← sama dengan transition_periods
     *   projection_periods: string[],           ← forwardMonths periode ke depan
     *   buckets: array<int, array{id:int, code:string, label:string}>,
     *   outstanding: array<int, array<string, float>>,   ← [bucket_id][period] = total outstanding
     *   transition: array<int, array<string, float>>,    ← [bucket_id][period] = transition rate (0–1)
     *   compound: array<int, array<string, float>>,      ← [bucket_id][period] = compound rate
     *   compound_avg: array<int, float>,                 ← [bucket_id] = PD bucket (avg compound)
     *   projection: array<int, array<string, float>>,    ← [bucket_id][period] = projected rate
     *   debtors: Collection,                             ← raw akun per bucket untuk drill-down
     * }
     */
    public function calculate(string $calculationPeriod, ?string $usageType = null): array
    {
        $window = $this->windowMonths($usageType);
        $forward = $this->forwardMonths($usageType);
        $projectionMethod = $this->projectionMethod($usageType);
        $lookbackMonths = $this->projectionLookbackMonths($usageType);

        // Rentang periode outstanding: calcPeriod-window s/d calcPeriod
        $outstandingStart = PeriodHelper::shiftBack($calculationPeriod, $window);
        $outstandingEnd = $calculationPeriod;
        $outstandingPeriods = PeriodHelper::range($outstandingStart, $outstandingEnd);

        // Rentang transition: bulan ke-2 outstanding s/d calcPeriod
        $transitionStart = PeriodHelper::shiftForward($outstandingStart, 1);
        $transitionPeriods = PeriodHelper::range($transitionStart, $outstandingEnd);

        // Compound diobservasi dari awal flow sampai T_end; rantai dapat memakai proyeksi.
        $compoundPeriods = PeriodHelper::range($transitionStart, $calculationPeriod);

        // Proyeksi 12 bulan: calcPeriod+1 s/d calcPeriod+12.
        $projStart = PeriodHelper::shiftForward($calculationPeriod, 1);
        $projEnd = PeriodHelper::shiftForward($calculationPeriod, $forward);
        $projectionPeriods = PeriodHelper::range($projStart, $projEnd);

        // Ambil semua bucket urut
        $buckets = Bucket::orderBy('bucket_order')->get();

        // Agregasi outstanding per bucket per periode dari historical
        // Baseline khusus PD Netflow: stsrec A atau W, POKPBY 03 hanya jika JTP.
        // Ref: AGENTS.md §4 — raw query builder untuk agregasi berat
        $rows = PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->join('buckets as b', function ($join) {
                    $join->whereRaw('fap.tgkhari >= b.min_days_overdue')
                        ->whereRaw('fap.tgkhari <= b.max_days_overdue');
                })
                ->whereIn('fap.period', $outstandingPeriods)
                ->when($usageType !== null, fn ($query) => $query->where('fa.usage_type', $usageType))
        )
            ->select(
                'fap.period',
                'b.id as bucket_id',
                DB::raw('SUM(fap.outstanding_balance) as total_outstanding')
            )
            ->groupBy('fap.period', 'b.id')
            ->get();

        // Build outstanding map [bucket_id][period] = float
        $outstanding = [];
        foreach ($rows as $row) {
            $outstanding[$row->bucket_id][$row->period] = (float) $row->total_outstanding;
        }

        // Fallback: bucket terakhir untuk tgkhari di luar rentang semua bucket
        // (baseline sama dengan query utama agar nominal penuh konsisten)
        $lastBucket = $buckets->last();
        $overflowRows = PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->leftJoin('buckets as b', function ($join) {
                    $join->whereRaw('fap.tgkhari >= b.min_days_overdue')
                        ->whereRaw('fap.tgkhari <= b.max_days_overdue');
                })
                ->when($usageType !== null, fn ($query) => $query->where('fa.usage_type', $usageType))
                ->whereIn('fap.period', $outstandingPeriods)
                ->whereNull('b.id')
        )
            ->select(
                'fap.period',
                DB::raw('SUM(fap.outstanding_balance) as total_outstanding')
            )
            ->groupBy('fap.period')
            ->get();

        foreach ($overflowRows as $row) {
            if ($lastBucket) {
                $existing = $outstanding[$lastBucket->id][$row->period] ?? 0.0;
                $outstanding[$lastBucket->id][$row->period] = $existing + (float) $row->total_outstanding;
            }
        }

        // Hitung transition rate: trans[bucketId][period] = outstanding[nextBucket][period] / outstanding[bucket][period-1]
        // Ref: PRD Bab 7 — B1→B2 periode P = outstanding B2 periode P / outstanding B1 periode P-1
        $transition = [];
        $bucketList = $buckets->values();
        $bucketCount = $bucketList->count();
        $transitionBuckets = $bucketList->slice(0, $bucketCount - 1); // B1..B13

        foreach ($transitionPeriods as $period) {
            $prevPeriod = PeriodHelper::shiftBack($period, 1);
            for ($i = 0; $i < $bucketCount - 1; $i++) {
                $fromBucket = $bucketList[$i];
                $toBucket = $bucketList[$i + 1];
                $srcOutstanding = $outstanding[$fromBucket->id][$prevPeriod] ?? 0.0;
                $destOutstanding = $outstanding[$toBucket->id][$period] ?? 0.0;
                $transition[$fromBucket->id][$period] = $srcOutstanding > 0
                    ? min(1.0, max(0.0, $destOutstanding / $srcOutstanding))
                    : 0.0;
            }
        }

        // Proyeksi rolling 6 bulan dihitung lebih dulu karena compound periode akhir
        // menggunakan rate proyeksi setelah T_end.
        $lookback = 6;
        foreach ($projectionPeriods as $projPeriod) {
            $lookbackEnd = PeriodHelper::shiftBack($projPeriod, 1);
            $lookbackStart = PeriodHelper::shiftBack($projPeriod, $lookback);
            $lookbackRange = PeriodHelper::range($lookbackStart, $lookbackEnd);

            foreach ($transitionBuckets as $bucket) {
                $values = array_map(
                    fn (string $period): float => $transition[$bucket->id][$period] ?? 0.0,
                    $lookbackRange,
                );
                $transition[$bucket->id][$projPeriod] = min(
                    1.0,
                    max(0.0, array_sum($values) / count($values)),
                );
            }
        }

        // Hitung compound flow loss setelah seluruh rate aktual dan proyeksi tersedia.
        // Ref: PRD Bab 7 — compound[B1][P] = trans[B1][P] x trans[B2][P+1] x ... x trans[B13][P+12]
        $compound = [];
        $compoundAvg = [];
        foreach ($compoundPeriods as $startPeriod) {
            foreach ($transitionBuckets as $idx => $bucket) {
                $product = 1.0;
                $valid = true;
                // values(): slice() mempertahankan key asli — tanpa reindex,
                // chainIdx mulai dari $idx (bukan 0) sehingga rantai bergeser +1 bulan.
                foreach ($transitionBuckets->slice($idx)->values() as $chainIdx => $chainBucket) {
                    $chainPeriod = PeriodHelper::shiftForward($startPeriod, $chainIdx);
                    $rate = $transition[$chainBucket->id][$chainPeriod] ?? null;
                    if ($rate === null) {
                        $valid = false;
                        break;
                    }
                    $product *= min(1.0, max(0.0, $rate));
                }
                $compound[$bucket->id][$startPeriod] = $valid ? min(1.0, $product) : 0.0;
            }
        }

        foreach ($transitionBuckets as $bucket) {
            $vals = array_filter(
                array_values($compound[$bucket->id] ?? []),
                fn (float $value): bool => $value >= 0.0,
            );
            $compoundAvg[$bucket->id] = count($vals) > 0
                ? min(1.0, array_sum($vals) / count($vals))
                : 0.0;
        }

        // Proyeksi: hitung rata-rata transition rate per periode proyeksi berdasarkan lookback dinamis.
        // mode 'rolling' : rata-rata N bulan terakhir sebelum periode proyeksi tsb (window geser per bulan)
        // mode 'full'    : rata-rata seluruh transitionPeriods s/d periode-1 dari periode proyeksi
        // Ref: PRD Bab 7 — projection_method & projection_lookback_months (configurable)
        $projection = [];
        foreach ($transitionBuckets as $bucket) {
            foreach ($projectionPeriods as $projPeriod) {
                // Batas atas lookback = periode tepat sebelum periode proyeksi
                $lookbackEnd = PeriodHelper::shiftBack($projPeriod, 1);

                if ($projectionMethod === 'full') {
                    // Semua periode transition dari awal window sampai lookbackEnd
                    $lookbackStart = $transitionStart;
                } else {
                    // Rolling: N bulan ke belakang dari lookbackEnd
                    $lookbackStart = PeriodHelper::shiftBack($lookbackEnd, $lookbackMonths - 1);
                }

                $lookbackPeriods = PeriodHelper::range($lookbackStart, $lookbackEnd);
                $vals = array_filter(
                    array_map(
                        fn (string $p) => $transition[$bucket->id][$p] ?? null,
                        $lookbackPeriods
                    ),
                    fn ($v) => $v !== null,
                );

                $projection[$bucket->id][$projPeriod] = count($vals) > 0
                    ? min(1.0, array_sum($vals) / count($vals))
                    : 0.0;
            }
        }

        $bucketsArr = $buckets->mapWithKeys(fn ($b) => [$b->id => [
            'id' => $b->id,
            'code' => $b->code,
            'label' => $b->label,
        ]])->toArray();

        return [
            'outstanding_periods' => $outstandingPeriods,
            'transition_periods' => $transitionPeriods,
            'compound_periods' => $compoundPeriods,
            'projection_periods' => $projectionPeriods,
            'buckets' => $bucketsArr,
            'outstanding' => $outstanding,
            'transition' => $transition,
            'compound' => $compound,
            'compound_avg' => $compoundAvg,
            'projection' => $projection,
        ];
    }
}
