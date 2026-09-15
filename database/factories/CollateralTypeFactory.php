<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CollateralType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollateralType>
 */
class CollateralTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->lexify('??')),
            'name' => $this->faker->words(2, true),
            'liquidation_discount_rate' => $this->faker->randomFloat(8, 0.1, 0.5),
            'is_active' => true,
        ];
    }
}
