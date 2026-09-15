<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdMigrationMatrix extends Model
{
    use SnapshotImmutability;

    protected $table = 'pd_migration_matrix';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'from_quality_grade_id',
        'to_quality_grade_id',
        'cohort_period',
        'migration_rate',
        'source_outstanding',
        'destination_outstanding',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'migration_rate' => 'decimal:8',
            'source_outstanding' => 'decimal:2',
            'destination_outstanding' => 'decimal:2',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }

    public function fromQualityGrade(): BelongsTo
    {
        return $this->belongsTo(QualityGrade::class, 'from_quality_grade_id');
    }

    /** Nullable — NULL means WO/absorbing state. */
    public function toQualityGrade(): BelongsTo
    {
        return $this->belongsTo(QualityGrade::class, 'to_quality_grade_id');
    }
}
