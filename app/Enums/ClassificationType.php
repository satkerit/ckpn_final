<?php

declare(strict_types=1);

namespace App\Enums;

enum ClassificationType: string
{
    case Individual = 'individual';
    case Collective = 'collective';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'CKPN Individual',
            self::Collective => 'CKPN Kolektif',
        };
    }
}
