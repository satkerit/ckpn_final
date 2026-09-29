<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\RunStatus;

/**
 * Guard snapshot model dari update/delete setelah run berstatus Completed/Failed/Approved.
 * Ref: AGENTS.md §4 — snapshot immutability via model event.
 * Ref: PRD Bab 13.2 — immutable snapshot setelah approval.
 *
 * Syarat: model yang menggunakan trait ini WAJIB punya relasi calculationRunLog().
 */
trait SnapshotImmutability
{
    public static function bootSnapshotImmutability(): void
    {
        static::updating(function (self $model): void {
            $runLog = $model->calculationRunLog;
            if ($runLog && in_array($runLog->status, [RunStatus::Completed, RunStatus::Failed, RunStatus::Approved], true)) {
                throw new \RuntimeException('Cannot update snapshot with completed/failed/approved run status. Ref: AGENTS.md §4');
            }
        });

        static::deleting(function (self $model): void {
            $runLog = $model->calculationRunLog;
            if ($runLog && in_array($runLog->status, [RunStatus::Completed, RunStatus::Approved], true)) {
                throw new \RuntimeException('Cannot delete snapshot with completed/approved run status. Ref: AGENTS.md §4');
            }
        });
    }
}
