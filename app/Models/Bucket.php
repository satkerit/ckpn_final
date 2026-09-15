<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BucketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bucket extends Model
{
    /** @use HasFactory<BucketFactory> */
    use HasFactory;

    protected $table = 'buckets';

    protected $fillable = [
        'code',
        'label',
        'min_days_overdue',
        'max_days_overdue',
        'bucket_order',
        'is_default_bucket',
    ];

    protected function casts(): array
    {
        return [
            'is_default_bucket' => 'boolean',
        ];
    }

    /** Ref: PRD Bab 7 */
    public function outstandingMonthly(): HasMany
    {
        return $this->hasMany(FinancingOutstandingMonthly::class);
    }

    public function dataQualityAnomalies(): HasMany
    {
        return $this->hasMany(DataQualityAnomaly::class);
    }
}
