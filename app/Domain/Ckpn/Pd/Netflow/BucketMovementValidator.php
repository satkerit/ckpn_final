<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use App\Enums\AnomalyType;
use App\Enums\UsageType;
use App\Models\Bucket;
use App\Models\DataQualityAnomaly;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates bucket movement data quality before PD Netflow calculation.
 * Ref: PRD Bab 7.3
 */
final class BucketMovementValidator
{
    /**
     * Memvalidasi kualitas data bucket movement untuk seluruh segmen pada rentang periode yang diberikan.
     *
     * Sumber data: financing_account_periods (via PdNetflowBaseline) — SAMA dengan sumber
     * yang dipakai PdNetflowCalculator::loadOutstandingMap() agar populasi konsisten.
     *
     * Jenis anomali yang dideteksi:
     * 1. EmptyBucket   : outstanding salah satu bucket = 0 pada suatu periode (kemungkinan data tidak terupload).
     * 2. DestinationExceedsSource : outstanding bucket pada periode t melebihi outstanding bucket sebelumnya
     *                              periode t-1 (unusual flow yang perlu dikonfirmasi; warning only).
     *
     * Kriteria input:
     * - $usageType : segmen pembiayaan (enum UsageType).
     * - $periods   : list periode urut ascending format yyyymm.
     * - $akadCodes : filter kode akad eligible (null = semua akad, harus konsisten dengan loadOutstandingMap).
     *
     * Return value:
     * - true  = tidak ada blocking anomaly.
     * - false = ada blocking anomaly.
     *
     * Ref: PRD Bab 7.3, FR-4.3
     *
     * @param  string[]  $periods  Ordered list of periods to validate (e.g. ['202212','202301',...])
     * @param  string[]|null  $akadCodes  Filter akad eligible — HARUS sama dengan yang dipakai kalkulator
     */
    public function validate(UsageType $usageType, array $periods, ?array $akadCodes = null): bool
    {
        $hasBlockingAnomaly = false;

        // Load semua bucket untuk mapping tgkhari → bucket_id
        $buckets = Bucket::orderBy('bucket_order')->get();

        // Bangun outstanding map per periode dari financing_account_periods (via PdNetflowBaseline)
        // agar konsisten dengan loadOutstandingMap() di PdNetflowCalculator.
        // Ref: AGENTS.md §4 — query builder untuk agregasi berat
        $outstandingMap = $this->buildOutstandingMapFromSource($usageType, $periods, $akadCodes, $buckets);

        foreach ($periods as $i => $period) {
            if ($i === 0) {
                continue; // Periode pertama = titik awal, tidak ada periode t-1 untuk dibandingkan
            }

            $prevPeriod = $periods[$i - 1];
            $currBuckets = $outstandingMap[$period] ?? [];
            $prevBuckets = $outstandingMap[$prevPeriod] ?? [];

            foreach ($buckets as $bucket) {
                $currOutstanding = $currBuckets[$bucket->id] ?? 0.0;
                $prevOutstanding = $prevBuckets[$bucket->id] ?? 0.0;

                // Check 1: Bucket kosong (empty bucket) — warning only, tidak blocking
                if ($currOutstanding === 0.0) {
                    $this->flagAnomaly(
                        period: $period,
                        usageType: $usageType->value,
                        bucketId: $bucket->id,
                        type: AnomalyType::EmptyBucket,
                        description: "Outstanding bucket {$bucket->code} kosong pada periode {$period}.",
                    );
                }

                // Check 2: Bucket tujuan > bucket asal (destination > source)
                // Bandingkan outstanding bucket ke-N periode t vs bucket ke-(N-1) periode t-1
                $prevBucketId = $this->getPrevBucketId($buckets, $bucket->bucket_order);
                if ($prevBucketId !== null) {
                    $prevBucketPrevPeriodOs = $prevBuckets[$prevBucketId] ?? 0.0;
                    if ($currOutstanding > $prevBucketPrevPeriodOs) {
                        $this->flagAnomaly(
                            period: $period,
                            usageType: $usageType->value,
                            bucketId: $bucket->id,
                            type: AnomalyType::DestinationExceedsSource,
                            description: "Outstanding bucket {$bucket->code} periode {$period} ({$currOutstanding}) melebihi outstanding bucket sebelumnya periode {$prevPeriod} ({$prevBucketPrevPeriodOs}).",
                        );
                        // Warning only — tidak auto-block (perlu review user — Ref: PRD Bab 7.3 FR-4.3)
                    }
                }
            }
        }

        return ! $hasBlockingAnomaly;
    }

    /**
     * Bangun outstanding map [period][bucket_id] = total_outstanding dari financing_account_periods.
     * Identik dengan loadOutstandingMap() di PdNetflowCalculator — sumber data yang sama
     * memastikan validasi berjalan pada populasi yang konsisten dengan kalkulasi.
     *
     * @param  string[]  $periods
     * @param  string[]|null  $akadCodes
     * @return array<string, array<int, float>>
     */
    private function buildOutstandingMapFromSource(
        UsageType $usageType,
        array $periods,
        ?array $akadCodes,
        Collection $buckets,
    ): array {
        $rows = PdNetflowBaseline::apply(
            DB::table('financing_account_periods as fap')
                ->join('financing_accounts as fa', 'fa.id', '=', 'fap.financing_account_id')
                ->where('fa.usage_type', $usageType->value)
                ->whereIn('fap.period', $periods),
            $akadCodes,
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
     * Ref: PRD Bab 5
     */
    private function resolveBucketFromTgkhari(int $tgkhari, Collection $buckets): int
    {
        foreach ($buckets as $bucket) {
            if ($tgkhari >= $bucket->min_days_overdue && $tgkhari <= $bucket->max_days_overdue) {
                return $bucket->id;
            }
        }

        return $buckets->last()->id;
    }

    /**
     * Kembalikan bucket_id dari bucket dengan order = currentOrder - 1.
     */
    private function getPrevBucketId(Collection $buckets, int $currentOrder): ?int
    {
        if ($currentOrder <= 1) {
            return null;
        }
        $prev = $buckets->firstWhere('bucket_order', $currentOrder - 1);

        return $prev?->id;
    }

    /**
     * Catat anomali ke tabel data_quality_anomalies (firstOrCreate — idempotent).
     *
     * $usageType bertipe int karena UsageType adalah int-backed enum dan kolom
     * usage_type di data_quality_anomalies adalah TINYINT UNSIGNED.
     */
    private function flagAnomaly(
        string $period,
        int $usageType,
        int $bucketId,
        AnomalyType $type,
        string $description,
    ): void {
        DataQualityAnomaly::firstOrCreate(
            [
                'period' => $period,
                'usage_type' => $usageType,
                'bucket_id' => $bucketId,
                'anomaly_type' => $type->value,
            ],
            [
                'description' => $description,
                'is_resolved' => false,
            ]
        );
    }
}
