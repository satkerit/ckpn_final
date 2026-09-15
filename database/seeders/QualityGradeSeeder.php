<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\QualityGrade;
use Illuminate\Database\Seeder;

/**
 * Seed kualitas pembiayaan (kolektibilitas 1-5).
 * Ref: PRD Bab 8
 */
class QualityGradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            ['code' => 'L', 'label' => 'Lancar', 'collectibility_number' => 1, 'is_npl' => false],
            ['code' => 'DPK', 'label' => 'Dalam Perhatian Khusus', 'collectibility_number' => 2, 'is_npl' => false],
            ['code' => 'KL', 'label' => 'Kurang Lancar', 'collectibility_number' => 3, 'is_npl' => true],
            ['code' => 'D', 'label' => 'Diragukan', 'collectibility_number' => 4, 'is_npl' => true],
            ['code' => 'M', 'label' => 'Macet', 'collectibility_number' => 5, 'is_npl' => true],
        ];

        foreach ($grades as $grade) {
            QualityGrade::firstOrCreate(['code' => $grade['code']], $grade);
        }
    }
}
