<?php

declare(strict_types=1);

namespace App\Domain\Ckpn\Lgd\Contracts;

use App\Enums\UsageType;

/**
 * Contract for LGD calculation methods.
 * Ref: PRD Bab 9 & 10
 */
interface LgdCalculationMethodInterface
{
    /**
     * Calculate LGD for a given segment and period.
     * Returns the LGD rate (0.0–1.0).
     */
    public function calculate(UsageType $usageType, string $calculationPeriod): float;
}
