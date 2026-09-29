<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\LgdCollateralShortfallBySegmentResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LgdCollateralShortfallBySegmentResult>
 */
class LgdCollateralShortfallBySegmentResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calculation_run_log_id' => CalculationRunLog::factory(),
            'usage_type' => $this->faker->randomElement([UsageType::ModalKerja, UsageType::Investasi, UsageType::Konsumsi]),
            'office_code' => $this->faker->bothify('OFC###'),
            'calculation_period' => $this->faker->numerify('20####'),
            'account_count' => $this->faker->numberBetween(10, 500),
            'total_outstanding' => $this->faker->randomFloat(2, 500_000_000, 10_000_000_000),
            'total_collateral_net_value' => $this->faker->randomFloat(2, 250_000_000, 7_000_000_000),
            'total_shortfall' => $this->faker->randomFloat(2, 0, 3_000_000_000),
            'avg_lgd_rate' => $this->faker->randomFloat(6, 0.01, 0.99),
            'notes' => $this->faker->sentence(),
        ];
    }
}
