<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Http\Export;

use Modules\Portfolios\Infrastructure\Http\Controllers\AdminPortfolioController;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * Maps a {@see PortfolioEloquentModel} row to the admin export columns, shared
 * by the CSV, Excel and PDF branches of {@see AdminPortfolioController::export}
 * so all three stay identical. The module ships only this row transform — the
 * writer / streamer / PDF renderer live behind the Shared `ExportPort`
 * (BACKEND-PHP §8).
 *
 * `Status` is the soft-delete axis (`Active` / `Suspended`, BACKEND-PHP §8),
 * not the `is_public` visibility flag (its own `Public` column).
 */
final readonly class PortfolioExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Title', 'Client', 'Type', 'Tech Stack', 'Public', 'Published', 'Owner', 'Created', 'Status'];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function toRow(PortfolioEloquentModel $portfolio): array
    {
        return $portfolio
            |> self::extract(...)
            |> self::formatDates(...)
            |> self::sanitize(...);
    }

    /**
     * @return array<string, string|null>
     */
    private static function extract(PortfolioEloquentModel $portfolio): array
    {
        return [
            'Title' => $portfolio->title,
            'Client' => $portfolio->client_name,
            'Type' => $portfolio->project_type,
            'Tech Stack' => implode(', ', $portfolio->tech_stack ?? []),
            'Public' => $portfolio->is_public ? 'Yes' : 'No',
            'Published' => $portfolio->published_at?->toIso8601String(),
            'Owner' => self::ownerName($portfolio),
            'Created' => $portfolio->created_at?->toIso8601String(),
            'Status' => $portfolio->deleted_at !== null ? 'Suspended' : 'Active',
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
        foreach (['Published', 'Created'] as $key) {
            if (is_string($row[$key]) && $row[$key] !== '') {
                try {
                    $row[$key] = (new \DateTimeImmutable($row[$key]))->format('F j, Y H:i');
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
        return array_map(
            static fn (?string $value): string => ($value === null || $value === '') ? '—' : $value,
            $row,
        );
    }

    private static function ownerName(PortfolioEloquentModel $portfolio): ?string
    {
        $owner = $portfolio->relationLoaded('user') ? $portfolio->user : null;

        if ($owner === null) {
            return null;
        }

        return trim(($owner->first_name ?? '').' '.($owner->last_name ?? '')) ?: null;
    }
}
