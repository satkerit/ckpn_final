<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Roles default — wajib sebelum user dibuat
        $this->call(RolesAndPermissionsSeeder::class);

        // Data master
        $this->call([
            RiskSegmentSeeder::class,
            CollateralTypeSeeder::class,
            QualityGradeSeeder::class,
            BucketSeeder::class,
            CalculationParameterSeeder::class,
        ]);

        // Admin user default
        $admin = User::firstOrCreate(
            ['email' => 'admin@ckpn.local'],
            [
                'name' => 'Admin CKPN',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('super_admin');
    }
}
