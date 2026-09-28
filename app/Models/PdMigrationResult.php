<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\HasOfficeSegmentScope;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdMigrationResult extends Model
{
    use HasOfficeSegmentScope;
    use SnapshotImmutability;

    protected $table = 'pd_migration_result';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'usage_type',
        'office_code',
        'from_quality_grade_id',
        'calculation_period',
        'pd_rate',
        'cohort_count',
        'data_period_start',
        'data_period_end',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'pd_rate' => 'decimal:8',
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
}
