<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CalculationRunLog;
use App\Models\CkpnIndividualResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CkpnIndividualResult>
 */
class CkpnIndividualResultFactory extends Factory
{
    public function definition(): array
    {
        $outstanding = $this->faker->randomFloat(2, 5_000_000, 500_000_000);
        $pdRate = $this->faker->randomFloat(8, 0.01, 0.3);
        $lgdRate = $this->faker->randomFloat(8, 0.1, 0.8);

        return [
            'calculation_run_log_id' => CalculationRunLog::factory(),
            'account_number' => $this->faker->numerify('ACC##########'),
            'usage_type' => $this->faker->randomElement([\App\Enums\UsageType::ModalKerja, \App\Enums\UsageType::Investasi, \App\Enums\UsageType::Konsumsi]),
            'office_code' => $this->faker->bothify('OFC###'),
            'akad_code' => $this->faker->bothify('AKD###'),
            'calculation_period' => $this->faker->numerify('20####'),
            'bucket' => $this->faker->numberBetween(0, 12),
            'days_past_due' => $this->faker->numberBetween(0, 720),
            'pd_rate' => $pdRate,
            'lgd_rate' => $lgdRate,
            'ckpn_rate' => $pdRate * $lgdRate,
            'outstanding' => $outstanding,
            'ckpn_amount' => $outstanding * $pdRate * $lgdRate,
            'notes' => $this->faker->sentence(),
        ];
    }
}
