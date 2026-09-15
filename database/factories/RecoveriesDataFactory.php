<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancingAccount;
use App\Models\RecoveriesData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecoveriesData>
 */
class RecoveriesDataFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financing_account_id' => FinancingAccount::factory(),
            'recovery_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'recovery_amount' => $this->faker->randomFloat(2, 1_000_000, 200_000_000),
            'period' => $this->faker->numerify('######'),
        ];
    }
}
