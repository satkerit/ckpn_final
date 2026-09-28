<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SnapshotImmutability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot hasil CKPN Individual — insert-only.
 * Ref: PRD Bab 6.1
 *
 * @property int $id
 * @property int $calculation_run_log_id
 * @property int $financing_account_id
 * @property string $calculation_period
 * @property string $outstanding_balance
 * @property string $total_collateral_liquidation_value
 * @property string $selling_cost_rate
 * @property string $selling_cost_amount
 * @property string $ckpn_amount
 * @property int $collectibility
 */
class CkpnIndividualResult extends Model
{
    use HasFactory, SnapshotImmutability;

    protected $table = 'ckpn_individual_result';

    /** Snapshot immutable — no updated_at. Ref: AGENTS.md §4 */
    public const UPDATED_AT = null;

    protected $fillable = [
        'office_code',
        'calculation_run_log_id',
        'financing_account_id',
        'calculation_period',
        'outstanding_balance',
        'total_collateral_liquidation_value',
        'selling_cost_rate',
        'selling_cost_amount',
        'ckpn_amount',
        'collectibility',
        'notes',
    ];

    protected $casts = [
        'outstanding_balance' => 'decimal:2',
        'total_collateral_liquidation_value' => 'decimal:2',
        'selling_cost_rate' => 'decimal:6',
        'selling_cost_amount' => 'decimal:2',
        'ckpn_amount' => 'decimal:2',
        'collectibility' => 'integer',
    ];

    public function calculationRunLog(): BelongsTo
    {
        return $this->belongsTo(CalculationRunLog::class);
    }

    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }
}
