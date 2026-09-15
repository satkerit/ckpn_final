<?php

declare(strict_types=1);

namespace App\Enums;

enum UsageType: int
{
    case ModalKerja = 1;
    case Investasi = 2;
    case Konsumsi = 3;

    public function label(): string
    {
        return match ($this) {
            UsageType::ModalKerja => 'Modal Kerja',
            UsageType::Investasi => 'Investasi',
            UsageType::Konsumsi => 'Konsumsi',
        };
    }
}
