<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Http\Export;

use Modules\Portfolios\Infrastructure\Http\Controllers\Api\PublicPortfolioController;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * Maps a published {@see PortfolioEloquentModel} row to the PUBLIC export
 * columns, shared by the CSV, Excel and PDF branches of
 * {@see PublicPortfolioController::export}.
 *
 * Deliberately narrower than {@see PortfolioExportTransformer}: no owner, no
 * soft-delete status — an unauthenticated download must not carry either
 * (OWASP §12). The feed query already restricts rows to public + published, so
 * every exported row is, by construction, "Active" and safe to publish.
 */
final readonly class PublicPortfolioExportTransformer
{
    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function headers(): array
    {
        return ['Title', 'Client', 'Type', 'Tech Stack', 'Live URL', 'Published'];
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
            'Live URL' => $portfolio->live_url,
            'Published' => $portfolio->published_at?->toIso8601String(),
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
        if (is_string($row['Published']) && $row['Published'] !== '') {
            try {
                $row['Published'] = (new \DateTimeImmutable($row['Published']))->format('F j, Y H:i');
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
        return array_map(
            static fn (?string $value): string => ($value === null || $value === '') ? '—' : $value,
            $row,
        );
    }
}
