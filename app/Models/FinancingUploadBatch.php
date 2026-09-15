<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UploadBatchStatus;
use Database\Factories\FinancingUploadBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancingUploadBatch extends Model
{
    /** @use HasFactory<FinancingUploadBatchFactory> */
    use HasFactory;

    protected $table = 'financing_upload_batches';

    protected $fillable = [
        'period',
        'upload_type',
        'filename',
        'uploaded_by_user_id',
        'uploaded_at',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'skipped_rows',
        'processed_rows',
        'status',
        'error_summary',
        'progress_log',
    ];

    protected function casts(): array
    {
        return [
            'status' => UploadBatchStatus::class,
            'uploaded_at' => 'datetime',
            'error_summary' => 'array',
        ];
    }

    /** Ref: PRD Bab 15 */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function accountPeriods(): HasMany
    {
        return $this->hasMany(FinancingAccountPeriod::class, 'upload_batch_id');
    }
}
