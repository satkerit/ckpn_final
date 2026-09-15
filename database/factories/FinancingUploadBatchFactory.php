<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingUploadBatch>
 */
class FinancingUploadBatchFactory extends Factory
{
    public function definition(): array
    {
        $total = $this->faker->numberBetween(100, 5000);
        $failed = $this->faker->numberBetween(0, 10);

        return [
            'period' => $this->faker->numerify('######'),
            'filename' => 'upload_'.$this->faker->numerify('######').'.xlsx',
            'uploaded_by_user_id' => User::factory(),
            'uploaded_at' => $this->faker->dateTimeThisYear(),
            'total_rows' => $total,
            'imported_rows' => $total - $failed,
            'failed_rows' => $failed,
            'status' => UploadBatchStatus::Done,
            'error_summary' => null,
        ];
    }
}
