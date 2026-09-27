<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\CollateralSaleDataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollateralSaleData extends Model
{
    /** @use HasFactory<CollateralSaleDataFactory> */
    use HasFactory;

    protected $table = 'collateral_sales_data';

    protected $fillable = [
        'collateral_id',
        'sale_date',
        'sale_amount',
        'period',
        'approved_by_user_id',
        'approval_status',
        'approved_at',
        'approval_notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'string',
            'sale_amount' => 'decimal:2',
            'approval_status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /** Ref: PRD Bab 10 */
    public function collateral(): BelongsTo
    {
        return $this->belongsTo(Collateral::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Cek apakah data ini sudah approved.
     */
    public function isApproved(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved;
    }

    /**
     * Cek apakah data ini pending approval.
     */
    public function isPending(): bool
    {
        return $this->approval_status === ApprovalStatus::Pending;
    }

    /**
     * Approve data ini.
     */
    public function approve(int $userId, ?string $notes = null): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->approval_status = ApprovalStatus::Approved;
        $this->approved_by_user_id = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;

        return $this->save();
    }

    /**
     * Reject data ini.
     */
    public function reject(int $userId, string $notes): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->approval_status = ApprovalStatus::Rejected;
        $this->approved_by_user_id = $userId;
        $this->approved_at = now();
        $this->approval_notes = $notes;

        return $this->save();
    }
}
