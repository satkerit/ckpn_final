<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis segmen pada segmentasi bertingkat.
 * Urutan level ditentukan oleh kolom level_order, bukan urutan case di enum ini.
 */
enum SegmentType: string
{
    case OfficeCode = 'office_code';
    case UsageType = 'usage_type';
    case AkadCode = 'akad_code';

    public function label(): string
    {
        return match ($this) {
            self::OfficeCode => 'Kode Kantor',
            self::UsageType => 'Jenis Penggunaan',
            self::AkadCode => 'Akad',
        };
    }

    /** Kolom sumber pada tabel financing_accounts. */
    public function sourceColumn(): string
    {
        return match ($this) {
            self::OfficeCode => 'office_code',
            self::UsageType => 'usage_type',
            self::AkadCode => 'akad_code',
        };
    }
}
