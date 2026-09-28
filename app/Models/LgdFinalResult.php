<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\HasOfficeSegmentScope;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot LGD final berbobot per segmen. Insert-only per periode.
 * Ref: PRD Bab 9, 10, 11
 */
class LgdFinalResult extends Model
{
    use HasOfficeSegmentScope;
    use SnapshotImmutability;

    protected $table = 'lgd_final_result';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'office_code',
        'calculation_period',
        'er_total_writeoff_amount',
        'er_total_recovery_amount',
        'cs_total_outstanding',
        'cs_total_shortfall',
        'total_recover',
        'total_os',
        'lgd_final_rate',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'er_total_writeoff_amount' => 'decimal:2',
            'er_total_recovery_amount' => 'decimal:2',
            'cs_total_outstanding' => 'decimal:2',
            'cs_total_shortfall' => 'decimal:2',
            'total_recover' => 'decimal:2',
            'total_os' => 'decimal:2',
            'lgd_final_rate' => 'decimal:6',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }
}
