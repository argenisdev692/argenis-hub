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
        return $backup
            |> self::extractColumns(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extractColumns(BackupEloquentModel $backup): array
    {
        return [
            'Filename' => $backup->filename,
            'Disk' => $backup->disk,
            'Size' => HumanBytes::format($backup->size_bytes),
            'Status' => $backup->status->label(),
            'Connection' => $backup->connection,
            'Started' => $backup->started_at?->toIso8601String(),
            'Finished' => $backup->finished_at?->toIso8601String(),
            'Created' => $backup->created_at?->toIso8601String(),
        ];
    }

    /**
     * ISO8601 → "August 27, 2026 02:00" (BACKEND-PHP §8 export date rule).
     *
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    private static function formatDates(array $row): array
    {
        foreach (['Started', 'Finished', 'Created'] as $field) {
            if (is_string($row[$field]) && $row[$field] !== '') {
                try {
                    $row[$field] = (new \DateTimeImmutable($row[$field]))->format('F j, Y H:i');
                } catch (\Exception) {
                    // keep the original value when parsing fails
                }
            }
        }

        return $row;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string>
     */
    private static function sanitize(array $row): array
    {
        return array_map(static fn (?string $value): string => $value ?? '—', $row);
    }
}
