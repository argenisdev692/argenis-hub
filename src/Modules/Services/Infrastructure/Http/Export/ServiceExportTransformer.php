<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Http\Export;

use Modules\ActivityLog\Infrastructure\Http\Export\ActivityLogExportTransformer;
use Modules\Services\Infrastructure\Http\Controllers\AdminServiceController;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * Maps a {@see ServiceEloquentModel} row to export columns, shared by the CSV,
 * Excel and PDF branches of {@see AdminServiceController::export}
 * so all three stay identical. The module ships only this row transform — the
 * writer / streamer / PDF renderer live behind the Shared `ExportPort`
 * (BACKEND-PHP §8), same split as {@see ActivityLogExportTransformer}.
 *
 * Status follows BACKEND-PHP §8 exactly: derived ONLY from `deleted_at`
 * (`Active` / `Suspended`). The editorial lifecycle flag (`is_active`) lives
 * in its own `Visibility` column and never leaks into `Status`.
 */
final readonly class ServiceExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Name', 'Slug', 'Description', 'Status', 'Visibility', 'Order', 'Created'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(ServiceEloquentModel $service): array
    {
        return $service
            |> self::extract(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, ?string>
     */
    private static function extract(ServiceEloquentModel $service): array
    {
        return [
            'Name' => $service->name,
            'Slug' => $service->slug,
            'Description' => $service->description,
            'Status' => $service->deleted_at !== null ? 'Suspended' : 'Active',
            'Visibility' => $service->is_active ? 'Visible' : 'Hidden',
            'Order' => (string) $service->sort_order,
            'Created' => $service->created_at?->toIso8601String(),
        ];
    }

    /**
     * Row dates render as `F j, Y` (e.g. "March 3, 2026") — BACKEND-PHP §8.
     * Accepts both ISO strings (from {@see self::extract()}) and
     * `DateTimeInterface` instances so callers can pre-format either way.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function formatDates(array $data): array
    {
        foreach (['Created'] as $field) {
            $value = $data[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            try {
                $data[$field] = $value instanceof \DateTimeInterface
                    ? \DateTimeImmutable::createFromInterface($value)->format('F j, Y')
                    : new \DateTimeImmutable((string) $value)->format('F j, Y');
            } catch (\Exception) {
                // Keep the original value if parsing fails.
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function sanitize(array $data): array
    {
        return array_map(static fn (mixed $value): string => (string) ($value ?? '—'), $data);
    }
}
