<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgdCollateralShortfallResult extends Model
{
    use SnapshotImmutability;

    protected $table = 'lgd_collateral_shortfall_result';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'financing_account_id',
        'usage_type',
        'calculation_period',
        'outstanding_balance',
        'collateral_net_value',
        'shortfall',
        'lgd_rate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'outstanding_balance' => 'decimal:2',
            'collateral_net_value' => 'decimal:2',
            'shortfall' => 'decimal:2',
            'lgd_rate' => 'decimal:8',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }

    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }
}
