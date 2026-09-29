<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculationSegmentationConfig extends Model
{
    protected $table = 'calculation_segmentation_configs';

    protected $fillable = [
        'method',
        'segment_dimensions',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'segment_dimensions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function getSegmentDimensions(string $method): array
    {
        return self::where('method', $method)
            ->where('is_active', true)
            ->firstOr(fn () => new self(['segment_dimensions' => []]))
            ->segment_dimensions ?? [];
    }
}

