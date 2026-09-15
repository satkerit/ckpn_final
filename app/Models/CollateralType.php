<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CollateralTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollateralType extends Model
{
    /** @use HasFactory<CollateralTypeFactory> */
    use HasFactory;

    protected $table = 'collateral_types';

    protected $fillable = [
        'code',
        'name',
        'liquidation_discount_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'liquidation_discount_rate' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    /** Ref: PRD Bab 10 */
    public function collaterals(): HasMany
    {
        return $this->hasMany(Collateral::class);
    }
}
