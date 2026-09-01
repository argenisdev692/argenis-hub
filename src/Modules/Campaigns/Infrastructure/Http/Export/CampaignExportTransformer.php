<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Http\Export;

use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Shared\Domain\Ports\ExportPort;

/**
 * Maps a {@see CampaignEloquentModel} row to export columns so CSV,
 * Excel and PDF stay consistent with the on-screen list. The module ships only
 * this transformer — the writer / streamer / PDF renderer live behind the Shared
 * {@see ExportPort} (BACKEND-PHP §8). Soft-delete state is `Status`
 * (Active/Suspended from `deleted_at`); campaign lifecycle stays in
 * `Campaign Status`. Per-platform copy and generated assets are intentionally
 * omitted — scoring + lifecycle metadata only.
 */
final readonly class CampaignExportTransformer
{
    /**
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Platform: string,
     *     Campaign Status: string,
     *     Success Probability: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    #[\NoDiscard]
    public static function transformForTable(CampaignEloquentModel $campaign): array
    {
        return $campaign
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Platform: string,
     *     Campaign Status: string,
     *     Success Probability: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    #[\NoDiscard]
    public static function transformForPdf(CampaignEloquentModel $campaign): array
    {
        return self::transformForTable($campaign);
    }

    /**
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Platform: string,
     *     Campaign Status: string,
     *     Success Probability: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }
     */
    private static function extractBaseData(CampaignEloquentModel $campaign): array
    {
        return [
            'Topic' => $campaign->topic,
            'Funnel Stage' => strtoupper($campaign->funnel_stage->value),
            'Platform' => ucfirst($campaign->platform->value),
            'Campaign Status' => ucfirst(str_replace('_', ' ', $campaign->status->value)),
            'Success Probability' => $campaign->overall_score_avg !== null
                ? "{$campaign->overall_score_avg}% ({$campaign->success_probability_label})"
                : '',
            'Scores Passed' => $campaign->all_scores_pass ? 'Yes' : 'No',
            'Created' => $campaign->created_at?->toIso8601String() ?? '',
            'Status' => $campaign->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @param  array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Platform: string,
     *     Campaign Status: string,
     *     Success Probability: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }  $data
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Platform: string,
     *     Campaign Status: string,
     *     Success Probability: string,
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
     *     Platform: string,
     *     Campaign Status: string,
     *     Success Probability: string,
     *     Scores Passed: string,
     *     Created: string,
     *     Status: string
     * }  $data
     * @return array{
     *     Topic: string,
     *     Funnel Stage: string,
     *     Platform: string,
     *     Campaign Status: string,
     *     Success Probability: string,
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
