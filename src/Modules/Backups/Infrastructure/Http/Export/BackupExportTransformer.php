<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Http\Export;

use Modules\Backups\Domain\Support\HumanBytes;
use Modules\Backups\Infrastructure\Http\Controllers\AdminBackupController;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

/**
 * Maps a {@see BackupEloquentModel} row to export columns, shared by the CSV,
 * Excel and PDF branches of {@see AdminBackupController::export} so all three
 * stay identical. The writer / streamer / PDF renderer live behind the Shared
 * `ExportPort` (BACKEND-PHP §8); this module ships only the row transform.
 *
 * `Status` is the run outcome (Completed / Failed / Running), not a soft-delete
 * state — the `backups` table is hard-deleted, so there is no Active / Suspended
 * column to derive.
 */
final readonly class BackupExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Filename', 'Disk', 'Size', 'Status', 'Connection', 'Started', 'Finished', 'Created'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(BackupEloquentModel $backup): array
    {
        return [
            'Filename' => $backup->filename,
            'Disk' => $backup->disk,
            'Size' => HumanBytes::format($backup->size_bytes),
            'Status' => $backup->status->label(),
            'Connection' => $backup->connection ?? '—',
            'Started' => $backup->started_at?->format('F j, Y H:i') ?? '—',
            'Finished' => $backup->finished_at?->format('F j, Y H:i') ?? '—',
            'Created' => $backup->created_at?->format('F j, Y H:i') ?? '—',
        ];
    }
}
