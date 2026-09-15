<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CollateralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collateral extends Model
{
    /** @use HasFactory<CollateralFactory> */
    use HasFactory;

    protected $table = 'collaterals';

    protected $fillable = [
        'financing_account_id',
        'collateral_code',
        'sequence_number',
        'collateral_type_id',
        'description',
        'appraisal_value',
        'estimated_sale_value',
        'appraised_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'appraisal_value' => 'decimal:2',
            'estimated_sale_value' => 'decimal:2',
            'appraised_at' => 'string',
            'is_active' => 'boolean',
        ];
    }

    /** Ref: PRD Bab 10 */
    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }

    public function collateralType(): BelongsTo
    {
        return $this->belongsTo(CollateralType::class);
    }

    public function salesData(): HasMany
    {
        return $this->hasMany(CollateralSaleData::class);
    }
}
