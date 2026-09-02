<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Export;

use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;
use Shared\Domain\Ports\ExportPort;

/**
 * Maps an {@see AvailabilityRuleEloquentModel} row to export columns so CSV,
 * Excel and PDF stay consistent with the on-screen list. The module ships only
 * this transformer — the writer / streamer / PDF renderer live behind the Shared
 * {@see ExportPort} (BACKEND-PHP §8). `Status` derives from
 * `deleted_at` only (Active / Suspended).
 */
final readonly class AvailabilityRuleExportTransformer
{
    /**
     * Carbon-aligned weekday index (0 = Sunday … 6 = Saturday).
     *
     * @var list<string>
     */
    private const array DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForTable(AvailabilityRuleEloquentModel $rule): array
    {
        return $rule
            |> self::extract(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForPdf(AvailabilityRuleEloquentModel $rule): array
    {
        return self::transformForTable($rule);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extract(AvailabilityRuleEloquentModel $rule): array
    {
        return [
            'Day' => self::DAYS[$rule->day_of_week] ?? null,
            'Start' => substr((string) $rule->start_time, 0, 5),
            'End' => substr((string) $rule->end_time, 0, 5),
            'Availability' => $rule->is_available ? 'Available' : 'Unavailable',
            'Status' => $rule->deleted_at !== null ? 'Suspended' : 'Active',
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
