<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Export;

use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;
use Shared\Domain\Ports\ExportPort;

/**
 * Maps an {@see AvailabilityExceptionEloquentModel} row to export columns so CSV,
 * Excel and PDF stay consistent with the on-screen list. The module ships only
 * this transformer — the writer / streamer / PDF renderer live behind the Shared
 * {@see ExportPort} (BACKEND-PHP §8). `Status` derives from
 * `deleted_at` only (Active / Suspended).
 */
final readonly class AvailabilityExceptionExportTransformer
{
    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForTable(AvailabilityExceptionEloquentModel $exception): array
    {
        return $exception
            |> self::extract(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForPdf(AvailabilityExceptionEloquentModel $exception): array
    {
        return self::transformForTable($exception);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extract(AvailabilityExceptionEloquentModel $exception): array
    {
        return [
            'Date' => $exception->date->format('F j, Y'),
            'Availability' => $exception->is_available ? 'Open' : 'Closed',
            'Start' => $exception->start_time !== null ? substr((string) $exception->start_time, 0, 5) : null,
            'End' => $exception->end_time !== null ? substr((string) $exception->end_time, 0, 5) : null,
            'Reason' => $exception->reason,
            'Source' => ucfirst($exception->source->value),
            'Status' => $exception->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    private static function sanitize(array $data): array
    {
        return array_map(static fn (?string $value): string => ($value === null || $value === '') ? '—' : $value, $data);
    }
}
