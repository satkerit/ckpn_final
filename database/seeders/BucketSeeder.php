<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bucket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed bucket hari tunggakan.
 * Ref: PRD Bab 7 — ketentuan bucket dikonfirmasi user (14 bucket).
 */
class BucketSeeder extends Seeder
{
    public function run(): void
    {
        $buckets = [
            ['code' => 'B1',  'label' => 'Lancar (0 hari)',    'min_days_overdue' => 0,   'max_days_overdue' => 0,   'bucket_order' => 1,  'is_default_bucket' => true],
            ['code' => 'B2',  'label' => '1-30 hari',          'min_days_overdue' => 1,   'max_days_overdue' => 30,  'bucket_order' => 2,  'is_default_bucket' => false],
            ['code' => 'B3',  'label' => '31-60 hari',         'min_days_overdue' => 31,  'max_days_overdue' => 60,  'bucket_order' => 3,  'is_default_bucket' => false],
            ['code' => 'B4',  'label' => '61-90 hari',         'min_days_overdue' => 61,  'max_days_overdue' => 90,  'bucket_order' => 4,  'is_default_bucket' => false],
            ['code' => 'B5',  'label' => '91-120 hari',        'min_days_overdue' => 91,  'max_days_overdue' => 120, 'bucket_order' => 5,  'is_default_bucket' => false],
            ['code' => 'B6',  'label' => '121-150 hari',       'min_days_overdue' => 121, 'max_days_overdue' => 150, 'bucket_order' => 6,  'is_default_bucket' => false],
            ['code' => 'B7',  'label' => '151-180 hari',       'min_days_overdue' => 151, 'max_days_overdue' => 180, 'bucket_order' => 7,  'is_default_bucket' => false],
            ['code' => 'B8',  'label' => '181-210 hari',       'min_days_overdue' => 181, 'max_days_overdue' => 210, 'bucket_order' => 8,  'is_default_bucket' => false],
            ['code' => 'B9',  'label' => '211-240 hari',       'min_days_overdue' => 211, 'max_days_overdue' => 240, 'bucket_order' => 9,  'is_default_bucket' => false],
            ['code' => 'B10', 'label' => '241-270 hari',       'min_days_overdue' => 241, 'max_days_overdue' => 270, 'bucket_order' => 10, 'is_default_bucket' => false],
            ['code' => 'B11', 'label' => '271-300 hari',       'min_days_overdue' => 271, 'max_days_overdue' => 300, 'bucket_order' => 11, 'is_default_bucket' => false],
            ['code' => 'B12', 'label' => '301-330 hari',       'min_days_overdue' => 301, 'max_days_overdue' => 330, 'bucket_order' => 12, 'is_default_bucket' => false],
            ['code' => 'B13', 'label' => '331-360 hari',       'min_days_overdue' => 331, 'max_days_overdue' => 360, 'bucket_order' => 13, 'is_default_bucket' => false],
            ['code' => 'B14', 'label' => '>360 hari + WO',     'min_days_overdue' => 361, 'max_days_overdue' => null, 'bucket_order' => 14, 'is_default_bucket' => false],
        ];

        // Hapus bucket lama — disable FK check sementara agar truncate bisa berjalan
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Bucket::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($buckets as $bucket) {
            Bucket::create($bucket);
        }
    }
}
