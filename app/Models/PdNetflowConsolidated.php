<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot PD Netflow Konsolidasi — agregasi semua segmen (weighted average).
 * Ref: PRD Bab 7
 */
class PdNetflowConsolidated extends Model
{
    use SnapshotImmutability;

    protected $table = 'pd_netflow_consolidated';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'from_bucket_id',
        'calculation_period',
        'pd_rate',
        'data_period_start',
        'data_period_end',
        'window_months',
        'transition_rate',
        'compound_rate',
        'source_outstanding',
        'destination_outstanding',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pd_rate' => 'decimal:8',
            'transition_rate' => 'decimal:8',
            'compound_rate' => 'decimal:8',
            'source_outstanding' => 'decimal:2',
            'destination_outstanding' => 'decimal:2',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }

    public function fromBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'from_bucket_id');
    }
}
