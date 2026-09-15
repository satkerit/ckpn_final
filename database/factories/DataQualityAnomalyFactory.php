<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnomalyType;
use App\Models\Bucket;
use App\Models\DataQualityAnomaly;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataQualityAnomaly>
 */
class DataQualityAnomalyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period' => $this->faker->numerify('######'),
            'usage_type' => $this->faker->randomElement([1, 2, 3]),
            'bucket_id' => Bucket::factory(),
            'anomaly_type' => $this->faker->randomElement(AnomalyType::cases()),
            'description' => $this->faker->sentence(),
            'is_resolved' => false,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ];
    }
}
