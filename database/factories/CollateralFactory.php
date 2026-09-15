<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Collateral;
use App\Models\CollateralType;
use App\Models\FinancingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collateral>
 */
class CollateralFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financing_account_id' => FinancingAccount::factory(),
            'collateral_type_id' => CollateralType::factory(),
            'collateral_code' => strtoupper($this->faker->unique()->bothify('AGN-####')),
            'description' => $this->faker->sentence(),
            'appraisal_value' => $this->faker->randomFloat(2, 50_000_000, 5_000_000_000),
            'estimated_sale_value' => $this->faker->randomFloat(2, 30_000_000, 4_000_000_000),
            'appraised_at' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'is_active' => true,
        ];
    }
}
