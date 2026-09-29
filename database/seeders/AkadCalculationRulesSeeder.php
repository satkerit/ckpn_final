<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AkadCalculationRule;
use Illuminate\Database\Seeder;

/**
 * Seed default akad calculation rules.
 * Semua akad default ke 'outstanding_balance'.
 * Admin bisa update via UI untuk akad yang pakai 'tgkmdl' (tunggakan pokok).
 * Ref: PRD Bab 5, 7, 8, User requirement #5
 */
class AkadCalculationRulesSeeder extends Seeder
{
    public function run(): void
    {
        $defaultRules = [
            ['akad_code' => '01', 'akad_name' => 'Murabahah', 'use_field' => 'outstanding_balance', 'description' => 'Jual beli dengan margin keuntungan yang disepakati'],
            ['akad_code' => '02', 'akad_name' => 'Mudharabah', 'use_field' => 'outstanding_balance', 'description' => 'Kemitraan modal dan pengelolaan'],
            ['akad_code' => '03', 'akad_name' => 'Musyarakah', 'use_field' => 'outstanding_balance', 'description' => 'Kemitraan modal bersama'],
            ['akad_code' => '04', 'akad_name' => 'Ijarah', 'use_field' => 'outstanding_balance', 'description' => 'Sewa guna usaha / ijarah muntahiya bittamlik'],
            ['akad_code' => '05', 'akad_name' => 'Istishna', 'use_field' => 'outstanding_balance', 'description' => 'Pesanan pembuatan barang'],
            ['akad_code' => '06', 'akad_name' => 'Salam', 'use_field' => 'outstanding_balance', 'description' => 'Jual beli dengan pembayaran di muka dan pengiriman di kemudian hari'],
            ['akad_code' => '07', 'akad_name' => 'Qardh', 'use_field' => 'outstanding_balance', 'description' => 'Pinjaman tanpa bunga/biaya'],
            ['akad_code' => '08', 'akad_name' => 'Rahn', 'use_field' => 'outstanding_balance', 'description' => 'Gadai / jaminan'],
            ['akad_code' => '09', 'akad_name' => 'Wakalah', 'use_field' => 'outstanding_balance', 'description' => 'Wakalah / perwakilan'],
            ['akad_code' => '10', 'akad_name' => 'Kafalah', 'use_field' => 'outstanding_balance', 'description' => 'Jaminan / penanggungan'],
            ['akad_code' => '11', 'akad_name' => 'Hibah', 'use_field' => 'outstanding_balance', 'description' => 'Hadiah / grant'],
            ['akad_code' => '12', 'akad_name' => 'Tabarru', 'use_field' => 'outstanding_balance', 'description' => 'Donasi / sumbangan sukarela'],
            ['akad_code' => '13', 'akad_name' => 'Wadi\'ah', 'use_field' => 'outstanding_balance', 'description' => 'Titipan amanah'],
            ['akad_code' => '14', 'akad_name' => 'Ujrah', 'use_field' => 'outstanding_balance', 'description' => 'Fee / upah jasa'],
        ];

        foreach ($defaultRules as $rule) {
            AkadCalculationRule::updateOrCreate(
                ['akad_code' => $rule['akad_code']],
                array_merge($rule, ['is_active' => true])
            );
        }

        $this->command->info('Seeded ' . count($defaultRules) . ' akad calculation rules (default: outstanding_balance).');
    }
}