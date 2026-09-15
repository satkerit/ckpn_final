<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Netflow;

use App\Enums\AnomalyType;
use App\Enums\UsageType;
use App\Models\DataQualityAnomaly;
use App\Models\FinancingOutstandingMonthly;
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
     * Fungsi ini dijalankan SEBELUM perhitungan PD Netflow dimulai. Setiap anomali yang ditemukan
     * akan dicatat ke tabel `data_quality_anomalies` agar dapat ditinjau oleh user melalui halaman
     * Data Quality.
     *
     * Jenis anomali yang dideteksi:
     * 1. EmptyBucket   : outstanding salah satu bucket = 0 pada suatu periode (kemungkinan data tidak terupload).
     * 2. BucketExceedsPrevious : outstanding bucket pada periode t melebihi outstanding periode t-1
     *                           (unusual flow yang perlu dikonfirmasi; bukan auto-block, hanya warning).
     *
     * Kriteria input:
     * - $usageType : segmen pembiayaan (enum UsageType).
     * - $periods   : list periode urut ascending format yyyymm, mis. ['202212','202301',...,'202512'].
     *               Periode pertama dipakai sebagai titik awal baseline (tidak divalidasi relatif ke sebelumnya).
     *
     * Return value:
     * - true  = tidak ada blocking anomaly (kalkulasi boleh dilanjutkan; warning mungkin tetap ada).
     * - false = ada blocking anomaly (kalkulasi sebaiknya ditahan sampai anomali diselesaikan).
     *
     * Ref: PRD Bab 7.3, FR-4.3
     *
     * @param  string[]  $periods  Ordered list of periods to validate (e.g. ['202212','202301',...,'202512'])
     */
    public function validate(UsageType $usageType, array $periods): bool
    {
        $hasBlockingAnomaly = false;

        // Load all outstanding data for this segment in this period range at once (performance)
        $outstandingData = FinancingOutstandingMonthly::where('usage_type', $usageType->value)
            ->whereIn('period', $periods)
            ->get()
            ->groupBy('period');  // keyed by period → Collection of records per period

        foreach ($periods as $i => $period) {
            if ($i === 0) {
                continue; // Periode pertama = titik awal, tidak ada periode t-1 untuk dibandingkan
            }

            $prevPeriod = $periods[$i - 1];
            $currRecords = $outstandingData->get($period, collect());
            $prevRecords = $outstandingData->get($prevPeriod, collect());

            // Check 1: Bucket kosong (empty bucket)
            foreach ($currRecords as $record) {
                if ((float) $record->total_outstanding === 0.0) {
                    $this->flagAnomaly(
                        period: $period,
                        usageType: $usageType->value,
                        bucketId: $record->bucket_id,
                        type: AnomalyType::EmptyBucket,
                        description: "Outstanding bucket {$record->bucket_id} kosong pada periode {$period}.",
                    );
                    // Empty bucket = warning only, not blocking
                }
            }

            // Check 2: Destination > Source (bucket_i period_t > bucket_{i-1} period_{t-1})
            foreach ($currRecords as $currRecord) {
                $prevRecord = $prevRecords->firstWhere('bucket_id', $currRecord->bucket_id - 1); // bucket sebelumnya

                if ($prevRecord === null) {
                    continue; // Bucket 1 tidak punya predecessor
                }

                if ((float) $currRecord->total_outstanding > (float) $prevRecord->total_outstanding) {
                    $this->flagAnomaly(
                        period: $period,
                        usageType: $usageType->value,
                        bucketId: $currRecord->bucket_id,
                        type: AnomalyType::DestinationExceedsSource,
                        description: "Outstanding bucket {$currRecord->bucket_id} periode {$period} ({$currRecord->total_outstanding}) melebihi outstanding bucket sebelumnya periode {$prevPeriod} ({$prevRecord->total_outstanding}).",
                    );
                    // Ini anomali warning, bukan auto-block (perlu approval user — lihat PRD Bab 7.3 FR-4.3)
                }
            }
        }

        return ! $hasBlockingAnomaly;
    }

    private function flagAnomaly(
        string $period,
        string $usageType,
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
