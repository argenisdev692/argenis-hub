<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Http\Export;

use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Shared\Domain\Ports\ExportPort;

/**
 * Maps a {@see SocialMediaContentEloquentModel} row to export columns so CSV,
 * Excel and PDF stay consistent with the on-screen list. The module ships only
 * this transformer — the writer / streamer / PDF renderer live behind the Shared
 * {@see ExportPort} (BACKEND-PHP §8). Soft-delete state is `Status`
 * (Active/Suspended from `deleted_at`); content lifecycle stays in
 * `Content Status`. Per-platform copy and generated assets are intentionally
 * omitted — scoring + lifecycle metadata only.
 */
final readonly class SocialMediaContentExportTransformer
{
    /**
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Content Status: string,
     *     Overall Score: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    #[\NoDiscard]
    public static function transformForTable(SocialMediaContentEloquentModel $content): array
    {
        return $content
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Content Status: string,
     *     Overall Score: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    #[\NoDiscard]
    public static function transformForPdf(SocialMediaContentEloquentModel $content): array
    {
        return self::transformForTable($content);
    }

    /**
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Content Status: string,
     *     Overall Score: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    private static function extractBaseData(SocialMediaContentEloquentModel $content): array
    {
        return [
            'Topic' => $content->topic,
            'Funnel Stage' => strtoupper($content->funnel_stage->value),
            'Content Status' => ucfirst(str_replace('_', ' ', $content->status->value)),
            'Overall Score' => $content->overall_score_avg !== null ? (string) $content->overall_score_avg : '',
            'Scores Passed' => $content->all_scores_pass ? 'Yes' : 'No',
            'Created' => $content->created_at?->toIso8601String() ?? '',
            'Status' => $content->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @param  array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Content Status: string,
     *     Overall Score: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }  $data
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Content Status: string,
     *     Overall Score: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    private static function formatDates(array $data): array
    {
        if ($data['Created'] !== '') {
            try {
                $data['Created'] = (new \DateTimeImmutable($data['Created']))->format('F j, Y');
            } catch (\Exception) {
                // Keep original value if parsing fails.
            }
        }

        return $data;
    }

    /**
     * @param  array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Content Status: string,
     *     Overall Score: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }  $data
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Content Status: string,
     *     Overall Score: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }
}
