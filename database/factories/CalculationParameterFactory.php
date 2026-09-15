<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalculationParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalculationParameter>
 */
class CalculationParameterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usage_type' => $this->faker->randomElement([1, 2, 3]),
            'parameter_key' => $this->faker->unique()->word(),
            'parameter_value' => (string) $this->faker->numberBetween(1, 99),
            'description' => $this->faker->sentence(),
        ];
    }
}
