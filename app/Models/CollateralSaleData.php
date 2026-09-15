<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CollateralSaleDataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollateralSaleData extends Model
{
    /** @use HasFactory<CollateralSaleDataFactory> */
    use HasFactory;

    protected $table = 'collateral_sales_data';

    protected $fillable = [
        'collateral_id',
        'sale_date',
        'sale_amount',
        'period',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'string',
            'sale_amount' => 'decimal:2',
        ];
    }

    /** Ref: PRD Bab 10 */
    public function collateral(): BelongsTo
    {
        return $this->belongsTo(Collateral::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
