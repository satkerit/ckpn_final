<?php

declare(strict_types=1);

namespace App\Enums;

enum PdMethod: string
{
    case Netflow = 'netflow';
    case Migration = 'migration';

    public function label(): string
    {
        return match ($this) {
            self::Netflow => 'PD Netflow',
            self::Migration => 'PD Migration',
        };
    }
}
