<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AkadCalculationRule;
use Illuminate\Database\Seeder;

/**
 * Seed default akad calculation rules.
 * Default: semua akad pakai outstanding_balance, kecuali exception list.
 * Ref: PRD Bab 5, User requirement #5
 */
class AkadCalculationRuleSeeder extends Seeder
{
    public function run(): void
    {
        // Default semua akad pakai outstanding_balance
        $defaultRules = [
            ['akad_code' => '01', 'akad_name' => 'Murabahah', 'use_field' => 'outstanding_balance'],
            ['akad_code' => '02', 'akad_name' => 'Musyarakah', 'use_field' => 'outstanding_balance'],
            ['akad_code' => '03', 'akad_name' => 'Mudharabah', 'use_field' => 'outstanding_balance'],
            ['akad_code' => '04', 'akad_name' => 'Ijarah', 'use_field' => 'outstanding_balance'],
            ['akad_code' => '05', 'akad_name' => 'Qard', 'use_field' => 'outstanding_balance'],
            // TODO: Konfirmasi user mana akad yang pakai tgkmdl (tunggakan pokok)
        ];

        foreach ($defaultRules as $rule) {
            AkadCalculationRule::updateOrCreate(
                ['akad_code' => $rule['akad_code']],
                [
                    'akad_name' => $rule['akad_name'],
                    'use_field' => $rule['use_field'],
                    'is_active' => true,
                    'description' => 'Default seeder — ' . ($rule['use_field'] === 'outstanding_balance' ? 'outstanding balance' : 'tunggakan pokok'),
                ]
            );
        }
    }
}
