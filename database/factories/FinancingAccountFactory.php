<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UsageType;
use App\Models\FinancingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingAccount>
 */
class FinancingAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_number' => $this->faker->unique()->numerify('ACC-#########'),
            'product_code' => $this->faker->randomElement(['MRB', 'KPR', 'KMK', 'KI']),
            'akad_code' => $this->faker->randomElement(['MRB', 'MMH', 'IJR', 'MDB']),
            'office_code' => $this->faker->lexify('KC???'),
            'economic_sector' => $this->faker->numerify('##'),
            'usage_type' => $this->faker->randomElement(UsageType::cases())->value,
            'customer_name' => $this->faker->name(),
            'is_active' => true,
        ];
    }
}
