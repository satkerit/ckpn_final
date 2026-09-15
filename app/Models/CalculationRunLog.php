<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Enums\UsageType;
use Database\Factories\CalculationRunLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationRunLog extends Model
{
    /** @use HasFactory<CalculationRunLogFactory> */
    use HasFactory;

    protected $table = 'calculation_run_log';

    protected $fillable = [
        'period',
        'run_type',
        'usage_type',
        'status',
        'triggered_by_user_id',
        'approved_by_user_id',
        'started_at',
        'completed_at',
        'notes',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'run_type' => RunType::class,
            'usage_type' => UsageType::class,
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
