<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Enums\UsageType;
use App\Models\Bucket;
use App\Models\DataQualityAnomaly;
use Illuminate\Support\Collection;

/**
 * Validates bucket movement data quality before PD Netflow calculation.
 * Ref: PRD Bab 7.3
 */
final class BucketMovementValidator
{
    /**
     * Memvalidasi kualitas data bucket movement untuk seluruh segmen pada rentang periode yang diberikan.
     *
     * Sumber data: financing_account_periods (via OutstandingMapLoader) — SAMA dengan sumber
     * yang dipakai PdNetflowCalculator agar populasi konsisten.
     *
     * Jenis anomali yang dideteksi:
     * 1. EmptyBucket   : outstanding salah satu bucket = 0 pada suatu periode (severity: CRITICAL — blocks calculation).
     * 2. DestinationExceedsSource : outstanding bucket pada periode t melebihi outstanding bucket sebelumnya
     *                              periode t-1 (severity: WARNING — allows continue with log).
     *
     * Kriteria input:
     * - $usageType : segmen pembiayaan (enum UsageType).
     * - $periods   : list periode urut ascending format yyyymm.
     * - $akadCodes : filter kode akad eligible (null = semua akad, harus konsisten dengan kalkulator).
     * - $officeCode: filter kode kantor level 1 (null = konsolidasi; harus konsisten dengan kalkulator).
     *
     * Return value:
     * - true  = tidak ada critical anomaly.
     * - false = ada critical anomaly (blocks calculation).
     *
     * Ref: PRD Bab 7.3, FR-4.3, Phase 3 Backend — Severity Categorization
     *
     * @param  string[]  $periods  Ordered list of periods to validate (e.g. ['202212','202301',...])
     * @param  string[]|null  $akadCodes  Filter akad eligible — HARUS sama dengan yang dipakai kalkulator
     * @param  string|null  $officeCode  Filter kantor — HARUS sama dengan yang dipakai kalkulator
     * @param  array<string, array<int, float>>|null  $outstandingMap  Map agregat dari kalkulator
     *                                                                 (null = aggregate ulang via loader)
     */
    public function validate(
        UsageType $usageType,
        array $periods,
        ?array $akadCodes = null,
        ?string $officeCode = null,
        ?array $outstandingMap = null,
    ): bool {
        $hasCriticalAnomaly = false;

        // Load semua bucket untuk mapping tgkhari → bucket_id
        $buckets = Bucket::orderBy('bucket_order')->get();

        // Outstanding map [period][bucket_id] = total_outstanding dari sumber yang sama
        // dengan kalkulator. Diteruskan dari kalkulator agar tidak query ganda;
        // jika null (pemakaian mandiri), di-aggregate ulang via OutstandingMapLoader.
        $outstandingMap ??= OutstandingMapLoader::load($usageType->value, $periods, $akadCodes, $officeCode)['map'];

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

                // Check 1: Bucket kosong (empty bucket) — CRITICAL, blocks calculation
                if ($currOutstanding === 0.0) {
                    $this->flagAnomaly(
                        period: $period,
                        usageType: $usageType->value,
                        bucketId: $bucket->id,
                        type: AnomalyType::EmptyBucket,
                        description: "Outstanding bucket {$bucket->code} kosong pada periode {$period}.",
                        severity: AnomalySeverity::Critical,
                    );
                    $hasCriticalAnomaly = true;
                }

                // Check 2: Bucket tujuan > bucket asal (destination > source) — WARNING only
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
                            severity: AnomalySeverity::Warning,
                        );
                    }
                }
            }
        }

        return ! $hasCriticalAnomaly;
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
        AnomalySeverity $severity,
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
                'severity' => $severity->value,
                'status' => 'pending',
            ]
        );
    }
}
