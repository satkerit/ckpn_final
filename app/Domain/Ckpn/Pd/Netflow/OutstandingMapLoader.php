<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use App\Domain\Ckpn\Services\PeriodHelper;
use App\Models\Bucket;
use App\Repositories\AkadCalculationRulesRepository;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi outstanding PD Netflow per periode × bucket langsung di SQL.
 *
 * Pengganti load baris-per-baris lama di PdNetflowCalculator/BucketMovementValidator
 * yang menyebabkan OOM (Worker STOPPED Memory limit exceeded) pada dataset besar.
 * Pola query mengikuti PdNetflowDetailService (agregasi SUM per bucket+periode),
 * dengan fallback: baris tgkhari di luar rentang semua bucket masuk bucket terakhir
 * (sama dengan perilaku resolveBucketFromTgkhari() sebelum penggantian ini).
 *
 * Ref: PRD Bab 7, AGENTS.md §4 (query builder untuk agregasi berat)
 */
final class OutstandingMapLoader
{
    /**
     * @param  string[]  $periods  Periode yyyymm urut
     * @param  string[]|null  $akadCodes  Filter kode akad eligible (null = semua)
     * @return array{map: array<string, array<int, float>>, row_count: int}
     *                                                                      map[period][bucket_id] = SUM(outstanding_balance); row_count = jumlah baris source
     *                                                                      yang dipakai baseline (untuk audit — dipakai job utk catatan "jumlah baris historis").
     */
    public static function load(int $usageTypeValue, array $periods, ?array $akadCodes = null, ?string $officeCode = null): array
    {
        $lastBucketId = Bucket::orderByDesc('bucket_order')->value('id');
        $repo = new AkadCalculationRulesRepository;

        // Map akad → field; default outstanding_balance
        $fieldMap = [];
        if ($akadCodes && count($akadCodes) > 0) {
            foreach ($akadCodes as $akadCode) {
                $fieldMap[$akadCode] = $repo->getCalculationField($akadCode);
            }
        }

        $rows = PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->leftJoin('buckets as b', function ($join): void {
                    $join->whereRaw('fap.tgkhari >= b.min_days_overdue')
                        ->whereRaw('fap.tgkhari <= b.max_days_overdue');
                })
                ->where('fa.usage_type', $usageTypeValue)
                ->whereIn('fap.period', $periods),
            $akadCodes,
            $officeCode,
        )
            ->select(
                'fap.period',
                'fa.akad_code',
                $lastBucketId !== null
                    ? DB::raw("COALESCE(b.id, {$lastBucketId}) as bucket_id")
                    : 'b.id as bucket_id',
                DB::raw('SUM(fap.outstanding_balance) as outstanding_balance'),
                DB::raw('SUM(fap.tgkmdl) as tgkmdl'),
                DB::raw('COUNT(*) as row_count'),
            )
            ->groupBy('fap.period', 'fa.akad_code', 'b.id')
            ->get();

        $map = [];
        $rowCount = 0;

        foreach ($rows as $row) {
            $bucketId = (int) $row->bucket_id;
            // Pilih field berdasarkan akad, default outstanding_balance
            $field = $fieldMap[$row->akad_code] ?? 'outstanding_balance';
            $total = (float) ($row->{$field} ?? 0.0);

            $map[$row->period][$bucketId] = ($map[$row->period][$bucketId] ?? 0.0) + $total;
            $rowCount += (int) $row->row_count;
        }

        return ['map' => $map, 'row_count' => $rowCount];
    }

    /**
     * Load outstanding detail breakdown per office_code + akad_code + bucket + periode.
     *
     * Return: array[office_code][akad_code][bucket_id][period] = total_outstanding
     * office_code='all' untuk aggregate semua kantor; akad_code='all' untuk semua akad.
     *
     * @param  string[]  $periods
     * @param  string[]|null  $akadCodes
     * @param  string|null  $officeCode  NULL = semua kantor; string = filter per kantor (level 1 segmentasi)
     * @return array<string, array<string, array<int, array<string, float>>>>
     */
    public static function loadDetailed(int $usageTypeValue, array $periods, ?array $akadCodes = null, ?string $officeCode = null): array
    {
        $lastBucketId = Bucket::orderByDesc('bucket_order')->value('id');
        $repo = new AkadCalculationRulesRepository;

        $fieldMap = [];
        if ($akadCodes && count($akadCodes) > 0) {
            foreach ($akadCodes as $akadCode) {
                $fieldMap[$akadCode] = $repo->getCalculationField($akadCode);
            }
        }

        $rows = PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->leftJoin('buckets as b', function ($join): void {
                    $join->whereRaw('fap.tgkhari >= b.min_days_overdue')
                        ->whereRaw('fap.tgkhari <= b.max_days_overdue');
                })
                ->where('fa.usage_type', $usageTypeValue)
                ->whereIn('fap.period', $periods),
            $akadCodes,
            $officeCode,  // segmentasi level 1 — filter per kantor jika diset
        )
            ->select(
                'fap.period',
                'fa.office_code',
                'fa.akad_code',
                $lastBucketId !== null
                    ? DB::raw("COALESCE(b.id, {$lastBucketId}) as bucket_id")
                    : 'b.id as bucket_id',
                DB::raw('SUM(fap.outstanding_balance) as outstanding_balance'),
                DB::raw('SUM(fap.tgkmdl) as tgkmdl'),
            )
            ->groupBy('fap.period', 'fa.office_code', 'fa.akad_code', 'b.id')
            ->get();

        $detail = [];

        foreach ($rows as $row) {
            $officeCode = $row->office_code ?? 'all';
            $akadCode = $row->akad_code ?? 'all';
            $bucketId = (int) $row->bucket_id;
            $period = $row->period;

            $field = $fieldMap[$akadCode] ?? 'outstanding_balance';
            $outstanding = (float) ($row->{$field} ?? 0.0);

            $detail[$officeCode][$akadCode][$bucketId][$period] = $outstanding;
        }

        return $detail;
    }

    /**
     * Compute transition rates per location+akad+bucket+period from detail outstanding.
     *
     * transition_rate[bucket_id][period] = outstanding[next_bucket_id][period] / outstanding[bucket_id][period]
     * Expanded per location+akad: [office_code][akad_code][bucket_id][period] = rate
     *
     * @param  array<string, array<string, array<int, array<string, float>>>>  $detailOutstanding  From loadDetailed()
     * @param  array<int, int>  $bucketIdByOrder  Mapping bucket_order → bucket_id
     * @return array<string, array<string, array<int, array<string, float>>>>
     */
    public static function computeDetailedTransitionRates(array $detailOutstanding, array $bucketIdByOrder): array
    {
        $detail = [];

        foreach ($detailOutstanding as $officeCode => $byAkad) {
            foreach ($byAkad as $akadCode => $byBucket) {
                foreach ($byBucket as $bucketId => $byPeriod) {
                    foreach ($byPeriod as $period => $srcOut) {
                        $nextBucketId = $bucketIdByOrder[$bucketId + 1] ?? null;
                        $dstOut = ($nextBucketId !== null && isset($detailOutstanding[$officeCode][$akadCode][$nextBucketId][$period]))
                            ? $detailOutstanding[$officeCode][$akadCode][$nextBucketId][$period]
                            : 0.0;

                        $rate = ($srcOut > 0) ? min(1.0, $dstOut / $srcOut) : 0.0;

                        if (! isset($detail[$officeCode])) {
                            $detail[$officeCode] = [];
                        }
                        if (! isset($detail[$officeCode][$akadCode])) {
                            $detail[$officeCode][$akadCode] = [];
                        }
                        if (! isset($detail[$officeCode][$akadCode][$bucketId])) {
                            $detail[$officeCode][$akadCode][$bucketId] = [];
                        }

                        $detail[$officeCode][$akadCode][$bucketId][$period] = $rate;
                    }
                }
            }
        }

        return $detail;
    }

    /**
     * Compute compound rates per location+akad+bucket+startPeriod from transition rates.
     *
     * compound[bucket_id][start_period] = rate(i→i+1, start) × rate(i+1→i+2, start+1) × ... × rate(13→14, start+n)
     * Expanded per location+akad: [office_code][akad_code][bucket_id][start_period] = compound_rate
     *
     * @param  array<string, array<string, array<int, array<string, float>>>>  $detailTransitionRates  From computeDetailedTransitionRates()
     * @param  array<int, int>  $bucketIdByOrder
     * @param  string[]  $compoundPeriods  Range of start_periods (rateStart..compoundEnd)
     * @return array<string, array<string, array<int, array<string, float>>>>
     */
    public static function computeDetailedCompoundRates(array $detailTransitionRates, array $bucketIdByOrder, array $compoundPeriods): array
    {
        $detail = [];

        foreach ($detailTransitionRates as $officeCode => $byAkad) {
            foreach ($byAkad as $akadCode => $byBucket) {
                foreach ($byBucket as $bucketId => $byPeriod) {
                    // Resolve bucket object to get bucket_order
                    $srcBucket = Bucket::find($bucketId);
                    if ($srcBucket === null) {
                        continue;
                    }

                    foreach ($compoundPeriods as $startPeriod) {
                        $compound = 1.0;
                        $curPeriod = $startPeriod;
                        $curOrder = $srcBucket->bucket_order;

                        while ($curOrder < 14) {
                            $curBucketId = $bucketIdByOrder[$curOrder] ?? null;
                            if ($curBucketId === null) {
                                break;
                            }

                            $rate = $detailTransitionRates[$officeCode][$akadCode][$curBucketId][$curPeriod] ?? 0.0;
                            $compound *= $rate;
                            $curOrder++;
                            $curPeriod = PeriodHelper::shiftForward($curPeriod, 1);
                        }

                        if (! isset($detail[$officeCode])) {
                            $detail[$officeCode] = [];
                        }
                        if (! isset($detail[$officeCode][$akadCode])) {
                            $detail[$officeCode][$akadCode] = [];
                        }
                        if (! isset($detail[$officeCode][$akadCode][$bucketId])) {
                            $detail[$officeCode][$akadCode][$bucketId] = [];
                        }

                        $detail[$officeCode][$akadCode][$bucketId][$startPeriod] = $compound;
                    }
                }
            }
        }

        return $detail;
    }

    /**
     * Load raw outstanding data (minimal structure) untuk dimension extraction.
     * Return: [['office_code' => 'K001', 'akad_code' => '01', 'bucket_id' => 1, 'period' => '202401', 'outstanding' => 1000], ...]
     */
    public static function loadRawForSegmentation(
        int $usageTypeValue,
        array $periods,
        ?array $akadCodes = null,
    ): array {
        $query = \DB::table('financing_account_periods')
            ->select('financing_offices.office_code', 'financing_accounts.akad_code', 'financing_account_periods.bucket_id', 'financing_account_periods.period', 'financing_account_periods.outstanding_balance')
            ->join('financing_accounts', 'financing_account_periods.financing_account_id', '=', 'financing_accounts.id')
            ->join('financing_offices', 'financing_accounts.financing_office_id', '=', 'financing_offices.id')
            ->where('financing_accounts.usage_type_id', '=', $usageTypeValue)
            ->whereIn('financing_account_periods.period', $periods);

        if ($akadCodes !== null && count($akadCodes) > 0) {
            $query->whereIn('financing_accounts.akad_code', $akadCodes);
        }

        return $query->get()->map(fn ($row) => [
            'office_code' => $row->office_code,
            'akad_code' => $row->akad_code,
            'bucket_id' => $row->bucket_id,
            'period' => $row->period,
            'outstanding' => (float) $row->outstanding_balance,
        ])->toArray();
    }
}

