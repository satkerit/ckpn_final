<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancingStatus;
use App\Enums\WriteoffStatus;
use Database\Factories\FinancingAccountPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingAccountPeriod extends Model
{
    /** @use HasFactory<FinancingAccountPeriodFactory> */
    use HasFactory;

    protected $table = 'financing_account_periods';

    protected $fillable = [
        'financing_account_id',
        'period',
        'outstanding_balance',
        'ppka',
        'collectibility',
        'tgkhari',
        'tgkmdl',
        'writeoff_date',
        'financing_status',
        'writeoff_status',
        'upload_batch_id',
        'office_code',
        'akad_code',
        'origination_date',
        'maturity_date',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_balance' => 'decimal:2',
            'ppka' => 'decimal:2',
            'writeoff_date' => 'string',
            'tgkmdl' => 'decimal:2',
            'origination_date' => 'string',
            'maturity_date' => 'string',
            'financing_status' => FinancingStatus::class,
            'writeoff_status' => WriteoffStatus::class,
        ];
    }

    /** Ref: PRD Bab 15 */
    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }

    public function uploadBatch(): BelongsTo
    {
        return $this->belongsTo(FinancingUploadBatch::class, 'upload_batch_id');
    }
}
