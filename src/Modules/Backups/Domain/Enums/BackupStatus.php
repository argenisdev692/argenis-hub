<?php

declare(strict_types=1);

namespace Modules\Backups\Domain\Enums;

/**
 * Lifecycle of a single database-backup attempt.
 *
 * `Running` is written when an on-demand run is dispatched, `Completed` once the
 * archive is confirmed on the backup disk, `Failed` when the runner exits
 * non-zero. There is no soft-delete state — a deleted archive is physically
 * gone, so the `backups` table carries no `deleted_at`.
 */
enum BackupStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
