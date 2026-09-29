<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LgdExpectedRecoveriesResult>
 */
class LgdExpectedRecoveriesResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calculation_run_log_id' => CalculationRunLog::factory(),
            'calculation_period' => $this->faker->numerify('20####'),
            'usage_type' => $this->faker->randomElement([UsageType::ModalKerja, UsageType::Investasi, UsageType::Konsumsi]),
            'office_code' => $this->faker->bothify('OFC###'),
            'lgd_rate' => $this->faker->randomFloat(6, 0.1, 0.9),
            'recovery_rate' => $this->faker->randomFloat(6, 0.1, 0.9),
            'window_years' => $this->faker->numberBetween(3, 7),
            'total_writeoff' => $this->faker->randomFloat(2, 100_000_000, 5_000_000_000),
            'total_recovery' => $this->faker->randomFloat(2, 10_000_000, 2_000_000_000),
            'notes' => $this->faker->sentence(),
        ];
    }
}
