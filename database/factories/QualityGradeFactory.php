<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QualityGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityGrade>
 */
class QualityGradeFactory extends Factory
{
    public function definition(): array
    {
        $collectibility = $this->faker->unique()->numberBetween(1, 5);

        return [
            'code' => 'K'.$collectibility,
            'label' => 'Kolektibilitas '.$collectibility,
            'collectibility_number' => $collectibility,
            'is_npl' => $collectibility >= 3,
        ];
    }
}
