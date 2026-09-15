<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bucket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bucket>
 */
class BucketFactory extends Factory
{
    public function definition(): array
    {
        static $order = 0;
        $order++;

        return [
            'code' => 'B'.$order,
            'label' => 'Bucket '.$order,
            'min_days_overdue' => ($order - 1) * 30,
            'max_days_overdue' => $order < 14 ? $order * 30 - 1 : null,
            'bucket_order' => $order,
            'is_default_bucket' => $order === 1,
        ];
    }
}
