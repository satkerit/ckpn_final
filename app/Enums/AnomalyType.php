<?php

declare(strict_types=1);

namespace App\Enums;

enum AnomalyType: string
{
    case EmptyBucket = 'empty_bucket';
    case DestinationExceedsSource = 'destination_exceeds_source';
    case NegativeOutstanding = 'negative_outstanding';

    public function getLabel(): string
    {
        return match ($this) {
            self::EmptyBucket => 'Bucket Kosong',
            self::DestinationExceedsSource => 'Tujuan > Asal',
            self::NegativeOutstanding => 'Outstanding Negatif',
        };
    }
}
