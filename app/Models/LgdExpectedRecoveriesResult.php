<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LgdExpectedRecoveriesResult extends Model
{
    use SnapshotImmutability;

    protected $table = 'lgd_expected_recoveries_result';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'calculation_period',
        'data_period_start',
        'data_period_end',
        'window_years',
        'total_writeoff_amount',
        'total_recovery_amount',
        'expected_recovery_rate',
        'lgd_rate',
        'is_all_account',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'total_writeoff_amount' => 'decimal:2',
            'total_recovery_amount' => 'decimal:2',
            'expected_recovery_rate' => 'decimal:8',
            'lgd_rate' => 'decimal:8',
            'is_all_account' => 'boolean',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }
}
