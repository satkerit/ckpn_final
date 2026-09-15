<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\RunStatus;

/**
 * Guard snapshot model dari update/delete setelah run berstatus Completed/Failed.
 * Ref: AGENTS.md §4 — snapshot immutability via model event.
 *
 * Syarat: model yang menggunakan trait ini WAJIB punya relasi calculationRunLog().
 */
trait SnapshotImmutability
{
    public static function bootSnapshotImmutability(): void
    {
        static::updating(function (self $model): void {
            $runLog = $model->calculationRunLog;
            if ($runLog && in_array($runLog->status, [RunStatus::Completed, RunStatus::Failed], true)) {
                throw new \RuntimeException('Cannot update snapshot with completed/failed run status. Ref: AGENTS.md §4');
            }
        });

        static::deleting(function (self $model): void {
            $runLog = $model->calculationRunLog;
            if ($runLog && $runLog->status === RunStatus::Completed) {
                throw new \RuntimeException('Cannot delete snapshot with completed run status. Ref: AGENTS.md §4');
            }
        });
    }
}
