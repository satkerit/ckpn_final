<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdNetflowBucketMovement extends Model
{
    protected $table = 'pd_netflow_bucket_movement';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'from_bucket_id',
        'period',
        'transition_rate',
        'is_projected',
        'source_outstanding',
        'destination_outstanding',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'transition_rate' => 'decimal:8',
            'source_outstanding' => 'decimal:2',
            'destination_outstanding' => 'decimal:2',
            'is_projected' => 'boolean',
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
