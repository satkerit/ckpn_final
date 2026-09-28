<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Topik penggunaan konfigurasi kolom pada modul Parameter Kalkulasi.
 * Ref: Permintaan customer — penentuan kolom untuk EAD, dasar PD, dasar CKPN, dan dasar LGD.
 */
enum ParameterMethod: string
{
    case Ead = 'ead';
    case Pd = 'pd';
    case Ckpn = 'ckpn';
    case Lgd = 'lgd';

    public function label(): string
    {
        return match ($this) {
            self::Ead => 'EAD',
            self::Pd => 'Dasar Perhitungan PD',
            self::Ckpn => 'Dasar Perhitungan CKPN',
            self::Lgd => 'Dasar Perhitungan LGD',
        };
    }
}
