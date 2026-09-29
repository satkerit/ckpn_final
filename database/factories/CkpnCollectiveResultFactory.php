<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalculationRunLog;
use App\Models\CkpnCollectiveResult;
use App\Models\FinancingAccount;
use App\Models\RiskSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CkpnCollectiveResult>
 */
class CkpnCollectiveResultFactory extends Factory
{
    public function definition(): array
    {
        $pdRate = $this->faker->randomFloat(6, 0.001, 0.3);
        $lgdRate = $this->faker->randomFloat(6, 0.1, 0.9);
        $ead = $this->faker->randomFloat(2, 1_000_000, 200_000_000);

        return [
            'calculation_run_log_id' => CalculationRunLog::factory(),
            'financing_account_id' => FinancingAccount::factory(),
            'risk_segment_id' => RiskSegment::factory(),
            'calculation_period' => $this->faker->numerify('20####'),
            'pd_method_used' => $this->faker->randomElement(['netflow', 'migration']),
            'pd_rate' => $pdRate,
            'lgd_method_used' => $this->faker->randomElement(['expected_recoveries', 'collateral_shortfall']),
            'lgd_rate' => $lgdRate,
            'ead' => $ead,
            'ckpn_amount' => $pdRate * $lgdRate * $ead,
            'pd_bucket_id' => null,
            'pd_quality_grade_id' => null,
        ];
    }
}
