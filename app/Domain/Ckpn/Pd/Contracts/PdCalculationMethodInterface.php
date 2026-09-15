<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Pd\Contracts;

use App\Enums\UsageType;

interface PdCalculationMethodInterface
{
    /**
     * Hitung PD untuk satu segmen pada periode tertentu.
     * Ref: PRD Bab 7 & 8
     *
     * @return array<int, float> Key = bucket_id, value = pd_rate
     */
    public function calculate(UsageType $usageType, string $calculationPeriod): array;
}
