<?php

declare(strict_types=1);

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            ApprovalStatus::Pending => 'Menunggu Approval',
            ApprovalStatus::Approved => 'Disetujui',
            ApprovalStatus::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            ApprovalStatus::Pending => 'warning',
            ApprovalStatus::Approved => 'success',
            ApprovalStatus::Rejected => 'danger',
        };
    }
}
