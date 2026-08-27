<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Infrastructure\Http\Export;

use Modules\ActivityLog\Application\DTOs\ActivityLogData;
use Spatie\Activitylog\Models\Activity;

/**
 * Maps an {@see Activity} row to export columns. Reuses {@see ActivityLogData}
 * for the actor / subject labels so CSV, Excel and PDF stay consistent with the
 * on-screen list (DRY). The module ships only this row transform — the
 * writer / streamer / PDF renderer live behind the Shared ExportPort
 * (BACKEND-PHP §8).
 */
final readonly class ActivityLogExportTransformer
{
    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForTable(Activity $activity): array
    {
        return $activity
            |> ActivityLogData::fromActivity(...)
            |> self::toColumns(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForPdf(Activity $activity): array
    {
        return self::transformForTable($activity);
    }

    /**
     * @return array<string, string|null>
     */
    private static function toColumns(ActivityLogData $log): array
    {
        return [
            'When' => $log->createdAt,
            'Actor' => $log->causerLabel,
            'Event' => $log->event,
            'Description' => $log->description,
            'Subject' => $log->subjectType,
            'Log' => $log->logName,
        ];
    }

    /**
     * ISO8601 → "March 3, 2026 14:05" (BACKEND-PHP §8 export date rule).
     *
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    private static function formatDates(array $row): array
    {
        if (is_string($row['When']) && $row['When'] !== '') {
            try {
                $row['When'] = (new \DateTimeImmutable($row['When']))->format('F j, Y H:i');
            } catch (\Exception) {
                // keep the original value when parsing fails
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
