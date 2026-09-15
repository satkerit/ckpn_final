<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClassificationType;
use App\Enums\UsageType;
use App\Models\CkpnPeriodClassification;
use App\Models\FinancingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CkpnPeriodClassification>
 */
class CkpnPeriodClassificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financing_account_id' => FinancingAccount::factory(),
            'period' => $this->faker->numerify('20####'),
            'classification' => ClassificationType::Individual->value,
            'outstanding_balance' => $this->faker->randomFloat(2, 1_000_000, 500_000_000),
            'collectibility' => $this->faker->numberBetween(3, 5),
            'financing_status' => null,
            'writeoff_status' => null,
            'usage_type' => $this->faker->randomElement(UsageType::cases())->value,
            'is_top_n_outstanding' => false,
            'classification_reason' => null,
            'ckpn_period_id' => null,
        ];
    }
}
