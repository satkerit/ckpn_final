<?php

declare(strict_types=1);

namespace App\Enums;

enum AnomalySeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            AnomalySeverity::Critical => 'Kritis',
            AnomalySeverity::Warning => 'Peringatan',
            AnomalySeverity::Info => 'Info',
        };
    }

    public function color(): string
    {
        return match ($this) {
            AnomalySeverity::Critical => 'danger',
            AnomalySeverity::Warning => 'warning',
            AnomalySeverity::Info => 'info',
        };
    }
}
