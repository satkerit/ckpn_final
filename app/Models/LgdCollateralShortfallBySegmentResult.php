<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot agregat LGD Collateral Shortfall per segmen.
 * Ref: PRD Bab 10 — melengkapi LgdCollateralShortfallResult (detail per akun).
 */
class LgdCollateralShortfallBySegmentResult extends Model
{
    use SnapshotImmutability;

    protected $table = 'lgd_collateral_shortfall_by_segment_result';

    public $timestamps = false;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'calculation_period',
        'account_count',
        'total_outstanding',
        'total_collateral_net_value',
        'total_shortfall',
        'avg_lgd_rate',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'account_count' => 'integer',
            'total_outstanding' => 'decimal:2',
            'total_collateral_net_value' => 'decimal:2',
            'total_shortfall' => 'decimal:2',
            'avg_lgd_rate' => 'decimal:6',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }
}
