<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bucket;
use App\Models\FinancingOutstandingMonthly;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingOutstandingMonthly>
 */
class FinancingOutstandingMonthlyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usage_type' => $this->faker->randomElement([1, 2, 3]),
            'bucket_id' => Bucket::factory(),
            'period' => $this->faker->numerify('######'),
            'total_outstanding' => $this->faker->randomFloat(2, 100_000, 100_000_000),
            'account_count' => $this->faker->numberBetween(1, 500),
        ];
    }
}
