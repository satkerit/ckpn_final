<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use Database\Factories\FinancingAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancingAccount extends Model
{
    /** @use HasFactory<FinancingAccountFactory> */
    use HasFactory;

    protected $table = 'financing_accounts';

    protected $fillable = [
        'account_number',
        'product_code',
        'akad_code',
        'office_code',
        'economic_sector',
        'usage_type',
        'customer_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'is_active' => 'boolean',
        ];
    }

    /** Ref: PRD Bab 5 */
    public function accountPeriods(): HasMany
    {
        return $this->hasMany(FinancingAccountPeriod::class);
    }

    public function segmentMaps(): HasMany
    {
        return $this->hasMany(FinancingAccountSegmentMap::class);
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(RecoveriesData::class);
    }

    public function collaterals(): HasMany
    {
        return $this->hasMany(Collateral::class);
    }
}
