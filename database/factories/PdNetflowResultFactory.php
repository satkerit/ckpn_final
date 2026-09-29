<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\PdNetflowResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdNetflowResult>
 */
class PdNetflowResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calculation_run_log_id' => CalculationRunLog::factory(),
            'calculation_period' => $this->faker->numerify('20####'),
            'usage_type' => $this->faker->randomElement([UsageType::ModalKerja, UsageType::Investasi, UsageType::Konsumsi]),
            'office_code' => $this->faker->bothify('OFC###'),
            'pd_rate' => $this->faker->randomFloat(6, 0.01, 0.5),
        ];
    }
}
