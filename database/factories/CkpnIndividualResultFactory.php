<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalculationRunLog;
use App\Models\CkpnIndividualResult;
use App\Models\FinancingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CkpnIndividualResult>
 */
class CkpnIndividualResultFactory extends Factory
{
    public function definition(): array
    {
        $outstanding = $this->faker->randomFloat(2, 5_000_000, 500_000_000);
        $liquidation = $this->faker->randomFloat(2, 0, $outstanding * 0.9);
        $sellingCostRate = $this->faker->randomFloat(6, 0.01, 0.1);
        $sellingCostAmount = $liquidation * $sellingCostRate;
        $ckpn = max(0.0, $outstanding - $liquidation - $sellingCostAmount);

        return [
            'calculation_run_log_id' => CalculationRunLog::factory(),
            'financing_account_id' => FinancingAccount::factory(),
            'calculation_period' => $this->faker->numerify('20####'),
            'outstanding_balance' => $outstanding,
            'total_collateral_liquidation_value' => $liquidation,
            'selling_cost_rate' => $sellingCostRate,
            'selling_cost_amount' => $sellingCostAmount,
            'ckpn_amount' => $ckpn,
            'collectibility' => $this->faker->randomElement([3, 4, 5]),
            'is_top_n_outstanding' => $this->faker->boolean(30),
        ];
    }
}
