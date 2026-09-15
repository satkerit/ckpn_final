<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Services;

use App\Models\Bucket;
use Illuminate\Database\Eloquent\Collection;

/**
 * Determines bucket from overdue days.
 * Ref: PRD Bab 7.2 — bucket 1-14 bertahap 30 hari (dikonfirmasi 2026-08-18)
 */
final class BucketingService
{
    /** @var Collection<int, Bucket> */
    private Collection $buckets;

    public function __construct()
    {
        $this->buckets = Bucket::orderBy('bucket_order')->get();
    }

    /**
     * Menentukan bucket_id berdasarkan jumlah hari tunggakan (overdue days).
     *
     * Kriteria penentuan bucket:
     * - Bucket 1 (lancar)  : overdueDays = 0
     * - Bucket 2–13        : overdueDays >= min_days_overdue DAN <= max_days_overdue
     *                        sesuai data tabel `buckets` (bertahap 30 hari per bucket, dikonfirmasi 2026-08-18)
     * - Bucket 14 (terakhir): catchall untuk WO / tunggakan sangat lama (max_days_overdue = NULL)
     *
     * Mengembalikan NULL jika tidak ada bucket yang cocok (indikasi masalah data — perlu dicek admin).
     *
     * Ref: PRD Bab 7.2
     */
    public function resolveBucketId(int $overdueDays): ?int
    {
        foreach ($this->buckets as $bucket) {
            $min = $bucket->min_days_overdue;
            $max = $bucket->max_days_overdue;

            if ($min === null && $overdueDays === 0) {
                return $bucket->id; // Bucket 1 = lancar
            }

            if ($min !== null && $overdueDays >= $min && ($max === null || $overdueDays <= $max)) {
                return $bucket->id;
            }
        }

        return null;
    }
}
