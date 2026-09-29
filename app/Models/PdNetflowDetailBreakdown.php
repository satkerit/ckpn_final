<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail breakdown PD Netflow calculation per lokasi + akad + jenis penggunaan.
 *
 * Ref: PRD Bab 7, Phase 3+ — Detailed pivot display of Outstanding, Transition Rate, Compound Rate
 * Snapshot: insert-only per calculation_run_log_id + usage_type + office_code + akad_code + bucket + period.
 */
class PdNetflowDetailBreakdown extends Model
{
    protected $table = 'pd_netflow_detail_breakdown';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'office_code',
        'akad_code',
        'from_bucket_id',
        'period',
        'outstanding_balance',
        'transition_rate',
        'compound_rate',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'outstanding_balance' => 'decimal:2',
            'transition_rate' => 'decimal:8',
            'compound_rate' => 'decimal:8',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'from_bucket_id');
    }
}
