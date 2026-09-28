<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdNetflowCalculationHistory extends Model
{
    protected $fillable = [
        'calculation_run_log_id',
        'calculation_period',
        'usage_type',
        'office_code',
        'history_data',
    ];

    protected function casts(): array
    {
        return [
            'history_data' => 'array',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }
}
