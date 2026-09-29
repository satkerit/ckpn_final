<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageType;
use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CkpnIndividualResult extends Model
{
    use SnapshotImmutability;

    protected $table = 'ckpn_individual_results';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    protected $fillable = [
        'calculation_run_log_id',
        'account_number',
        'usage_type',
        'office_code',
        'akad_code',
        'calculation_period',
        'bucket',
        'days_past_due',
        'pd_rate',
        'lgd_rate',
        'ckpn_rate',
        'outstanding',
        'ckpn_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'usage_type' => UsageType::class,
            'pd_rate' => 'decimal:8',
            'lgd_rate' => 'decimal:8',
            'ckpn_rate' => 'decimal:8',
            'outstanding' => 'decimal:2',
            'ckpn_amount' => 'decimal:2',
        ];
    }

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }
}
