<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RiskSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskSegment extends Model
{
    /** @use HasFactory<RiskSegmentFactory> */
    use HasFactory;

    protected $table = 'risk_segments';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Ref: PRD Bab 5 */
    public function accountSegmentMaps(): HasMany
    {
        return $this->hasMany(FinancingAccountSegmentMap::class);
    }
}
