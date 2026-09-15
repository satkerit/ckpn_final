<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Models\CalculationRunLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalculationRunLog>
 */
class CalculationRunLogFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subMinutes(rand(1, 60));

        return [
            'period' => (string) $this->faker->numberBetween(202001, 202512),
            'run_type' => RunType::cases()[array_rand(RunType::cases())],
            'usage_type' => [1, 2, 3][array_rand([1, 2, 3])],
            'status' => RunStatus::Completed,
            'triggered_by_user_id' => User::factory(),
            'approved_by_user_id' => null,
            'started_at' => $startedAt,
            'completed_at' => $startedAt->copy()->addMinutes(rand(1, 30)),
            'notes' => null,
            'error_message' => null,
        ];
    }
}
