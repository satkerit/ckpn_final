<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RiskSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskSegment>
 */
class RiskSegmentFactory extends Factory
{
    public function definition(): array
    {
        static $i = 0;
        $i++;

        return [
            'code' => 'SEG-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'name' => 'Segmen '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
