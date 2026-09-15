<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClassificationType;
use App\Enums\FinancingStatus;
use App\Enums\UsageType;
use App\Enums\WriteoffStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil klasifikasi data pembiayaan per periode — individual vs kolektif.
 * Diisi oleh ClassifyPeriodDataJob, insert-only per periode.
 * Ref: PRD Bab 6.1, 12a Step 3
 */
class CkpnPeriodClassification extends Model
{
    use HasFactory;

    protected $table = 'ckpn_period_classifications';

    public $timestamps = false;

    protected $fillable = [
        'period',
        'financing_account_id',
        'classification',
        'outstanding_balance',
        'collectibility',
        'financing_status',
        'writeoff_status',
        'usage_type',
        'classification_reason',
        'ckpn_period_id',
    ];

    protected function casts(): array
    {
        return [
            'classification' => ClassificationType::class,
            'outstanding_balance' => 'decimal:2',
            'financing_status' => FinancingStatus::class,
            'writeoff_status' => WriteoffStatus::class,
            'usage_type' => UsageType::class,
            'created_at' => 'datetime',
        ];
    }

    public function ckpnPeriod(): BelongsTo
    {
        return $this->belongsTo(CkpnPeriod::class, 'ckpn_period_id');
    }

    public function financingAccount(): BelongsTo
    {
        return $this->belongsTo(FinancingAccount::class);
    }
}
