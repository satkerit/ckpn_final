<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use Database\Factories\FinancingOutstandingMonthlyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingOutstandingMonthly extends Model
{
    /** @use HasFactory<FinancingOutstandingMonthlyFactory> */
    use HasFactory;

    protected $table = 'financing_outstanding_monthly';

    protected $fillable = [
        'usage_type',
        'bucket_id',
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

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }
}
