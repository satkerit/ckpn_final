<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Enums\UsageType;
use Database\Factories\DataQualityAnomalyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityAnomaly extends Model
{
    /** @use HasFactory<DataQualityAnomalyFactory> */
    use HasFactory;

    protected $table = 'data_quality_anomalies';

    protected $fillable = [
        'period',
        'usage_type',
        'bucket_id',
        'anomaly_type',
        'description',
        'severity',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'source_table',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'anomaly_type' => AnomalyType::class,
            'severity' => AnomalySeverity::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getUsageTypeLabelAttribute(): string
    {
        return $this->usage_type ? $this->usage_type->label() : 'N/A';
    }
}
