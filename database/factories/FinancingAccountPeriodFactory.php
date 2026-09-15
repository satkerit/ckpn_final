<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FinancingStatus;
use App\Models\FinancingAccount;
use App\Models\FinancingAccountPeriod;
use App\Models\FinancingUploadBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingAccountPeriod>
 */
class FinancingAccountPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financing_account_id' => FinancingAccount::factory(),
            'period' => '202601',
            'outstanding_balance' => $this->faker->randomFloat(2, 1000, 1_000_000),
            'collectibility' => $this->faker->numberBetween(1, 5),
            'writeoff_date' => null,
            'financing_status' => FinancingStatus::Aktif,
            'writeoff_status' => null,
            'upload_batch_id' => FinancingUploadBatch::factory(),
        ];
    }
}
