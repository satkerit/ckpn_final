<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot hasil CKPN Kolektif — insert-only.
 * Ref: PRD Bab 11
 *
 * @property int $id
 * @property int $calculation_run_log_id
 * @property int $financing_account_id
 * @property string $usage_type
 * @property string $calculation_period
 * @property string $pd_method_used
 * @property string $pd_rate
 * @property string $lgd_method_used
 * @property string $lgd_rate
 * @property string $ead
 * @property string $ckpn_amount
 */
class CkpnCollectiveResult extends Model
{
    use HasFactory, SnapshotImmutability;

    protected $table = 'ckpn_collective_result';

    /** Snapshot immutable — no updated_at. Ref: AGENTS.md §4 */
    public const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'financing_account_id',
        'usage_type',
        'calculation_period',
        'pd_method_used',
        'pd_rate',
        'lgd_method_used',
        'lgd_rate',
        'ead',
        'pd_bucket_id',
        'pd_quality_grade_id',
        'ckpn_amount',
        'notes',
    ];

    protected $casts = [
        'usage_type' => UsageType::class,
        'pd_rate' => 'decimal:6',
        'lgd_rate' => 'decimal:6',
        'ead' => 'decimal:2',
        'pd_bucket_id' => 'integer',
        'pd_quality_grade_id' => 'integer',
        'ckpn_amount' => 'decimal:2',
    ];

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }

    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }

    /** Relasi ke bucket (hanya terisi saat pd_method_used='netflow') */
    public function pdBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'pd_bucket_id');
    }
}
