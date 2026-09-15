<?php

declare(strict_types=1);

namespace App\Models;

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
        'is_resolved',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'anomaly_type' => AnomalyType::class,
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
