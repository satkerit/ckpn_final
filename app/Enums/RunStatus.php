<?php

declare(strict_types=1);

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithWarning = 'completed_with_warning';
    case Failed = 'failed';
    case Approved = 'approved';
}
