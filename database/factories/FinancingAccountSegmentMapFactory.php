<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinancingAccount;
use App\Models\FinancingAccountSegmentMap;
use App\Models\RiskSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingAccountSegmentMap>
 */
class FinancingAccountSegmentMapFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financing_account_id' => FinancingAccount::factory(),
            'risk_segment_id' => RiskSegment::factory(),
            'effective_from' => $this->faker->dateTimeBetween('-3 years', '-1 year')->format('Ym'),
            'effective_to' => null,
        ];
    }
}
