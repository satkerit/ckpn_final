<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use Database\Factories\FinancingOutstandingQuarterlyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingOutstandingQuarterly extends Model
{
    /** @use HasFactory<FinancingOutstandingQuarterlyFactory> */
    use HasFactory;

    protected $table = 'financing_outstanding_quarterly';

    protected $fillable = [
        'usage_type',
        'quality_grade_id',
        'period',
        'total_outstanding',
        'account_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'total_outstanding' => 'decimal:2',
        ];
    }

    public function qualityGrade(): BelongsTo
    {
        return $this->belongsTo(QualityGrade::class);
    }
}
