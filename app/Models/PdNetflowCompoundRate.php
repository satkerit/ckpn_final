<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\HasOfficeSegmentScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdNetflowCompoundRate extends Model
{
    use HasOfficeSegmentScope;

    protected $table = 'pd_netflow_compound_rate';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'office_code',
        'akad_code',
        'from_bucket_id',
        'start_period',
        'compound_rate',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'compound_rate' => 'decimal:8',
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
