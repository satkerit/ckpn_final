<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UsageType;
use App\Models\CalculationRunLog;
use App\Models\FinancingAccount;
use App\Models\LgdCollateralShortfallResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LgdCollateralShortfallResult>
 */
class LgdCollateralShortfallResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calculation_run_log_id' => CalculationRunLog::factory(),
            'financing_account_id' => FinancingAccount::factory(),
            'usage_type' => $this->faker->randomElement([UsageType::ModalKerja, UsageType::Investasi, UsageType::Konsumsi]),
            'office_code' => $this->faker->bothify('OFC###'),
            'calculation_period' => $this->faker->numerify('20####'),
            'outstanding_balance' => $this->faker->randomFloat(2, 100_000_000, 5_000_000_000),
            'collateral_net_value' => $this->faker->randomFloat(2, 50_000_000, 3_000_000_000),
            'shortfall' => $this->faker->randomFloat(2, 0, 2_000_000_000),
            'lgd_rate' => $this->faker->randomFloat(8, 0.01, 0.99),
            'notes' => $this->faker->sentence(),
        ];
    }
}
