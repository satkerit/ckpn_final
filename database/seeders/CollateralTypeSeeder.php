<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CollateralType;
use Illuminate\Database\Seeder;

/**
 * Seed tipe jaminan default dengan discount rate.
 * Ref: PRD Bab 10.2
 */
class CollateralTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'SHM', 'name' => 'Sertifikat Hak Milik', 'liquidation_discount_rate' => 0.20],
            ['code' => 'SHGB', 'name' => 'Sertifikat HGB', 'liquidation_discount_rate' => 0.25],
            ['code' => 'BPKB', 'name' => 'BPKB Kendaraan', 'liquidation_discount_rate' => 0.40],
            ['code' => 'DEPO', 'name' => 'Deposito', 'liquidation_discount_rate' => 0.00],
            ['code' => 'EMSN', 'name' => 'Emas/Perhiasan', 'liquidation_discount_rate' => 0.15],
            ['code' => 'MSN', 'name' => 'Mesin/Peralatan', 'liquidation_discount_rate' => 0.50],
        ];

        foreach ($types as $type) {
            CollateralType::firstOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
