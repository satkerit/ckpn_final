<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RiskSegment;
use Illuminate\Database\Seeder;

/**
 * Seed segmen risiko default.
 * Ref: PRD Bab 5
 */
class RiskSegmentSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            ['code' => 'MRB', 'name' => 'Mikro Ritel B', 'description' => 'Pembiayaan mikro ritel segmen B'],
            ['code' => 'MRA', 'name' => 'Mikro Ritel A', 'description' => 'Pembiayaan mikro ritel segmen A'],
            ['code' => 'KCL', 'name' => 'Komersial Kecil', 'description' => 'Pembiayaan komersial kecil'],
            ['code' => 'KMN', 'name' => 'Komersial Menengah', 'description' => 'Pembiayaan komersial menengah'],
            ['code' => 'KBB', 'name' => 'Konsumer Berbasis Bisnis', 'description' => 'Pembiayaan konsumer berbasis bisnis'],
            ['code' => 'KPN', 'name' => 'Konsumer Payroll', 'description' => 'Pembiayaan konsumer payroll/gaji'],
        ];

        foreach ($segments as $segment) {
            RiskSegment::firstOrCreate(
                ['code' => $segment['code']],
                $segment
            );
        }
    }
}
