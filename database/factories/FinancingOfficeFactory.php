<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancingOffice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingOffice>
 */
class FinancingOfficeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->lexify('KC???')),
            'name' => 'KC '.$this->faker->city(),
            'is_active' => true,
        ];
    }
}
