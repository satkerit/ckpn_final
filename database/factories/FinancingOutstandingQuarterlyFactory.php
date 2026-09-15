<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancingOutstandingQuarterly;
use App\Models\QualityGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingOutstandingQuarterly>
 */
class FinancingOutstandingQuarterlyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usage_type' => $this->faker->randomElement([1, 2, 3]),
            'quality_grade_id' => QualityGrade::factory(),
            'period' => $this->faker->numerify('######'),
            'total_outstanding' => $this->faker->randomFloat(2, 100_000, 100_000_000),
            'account_count' => $this->faker->numberBetween(1, 500),
        ];
    }
}
