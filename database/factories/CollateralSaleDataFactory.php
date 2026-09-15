<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Collateral;
use App\Models\CollateralSaleData;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollateralSaleData>
 */
class CollateralSaleDataFactory extends Factory
{
    public function definition(): array
    {
        return [
            'collateral_id' => Collateral::factory(),
            'sale_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'sale_amount' => $this->faker->randomFloat(2, 20_000_000, 3_000_000_000),
            'period' => $this->faker->numerify('######'),
            'approved_by_user_id' => User::factory(),
        ];
    }
}
