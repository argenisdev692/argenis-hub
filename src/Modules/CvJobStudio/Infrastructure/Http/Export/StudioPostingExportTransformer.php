<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Export;

use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;

/**
 * Maps a posting row to Excel / PDF columns (BACKEND-PHP §8). Status derives
 * from `deleted_at` as Active/Suspended; scores come from the latest stored
 * score, never recomputed during export.
 */
final readonly class StudioPostingExportTransformer
{
    /**
     * @return array{Title: string, Employer: string, Location: string, Remote scope: string, Status: string, Score: string, Band: string, Cap: string, Channel: string, Created: string}
     */
    #[\NoDiscard]
    public static function transformForExcel(StudioPostingEloquentModel $posting): array
    {
        return $posting
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{Title: string, Employer: string, Location: string, Remote scope: string, Status: string, Score: string, Band: string, Cap: string, Channel: string, Created: string}
     */
    #[\NoDiscard]
    public static function transformForPdf(StudioPostingEloquentModel $posting): array
    {
        return self::transformForExcel($posting);
    }

    /**
     * @return array{Title: string, Employer: string, Location: string, Remote scope: string, Status: string, Score: string, Band: string, Cap: string, Channel: string, Created: string}
     */
    private static function extractBaseData(StudioPostingEloquentModel $posting): array
    {
        $latest = $posting->relationLoaded('scores') ? $posting->scores->first() : null;

        return [
            'Title' => $posting->title,
            'Employer' => $posting->employer_name ?? '—',
            'Location' => $posting->location_text ?? '—',
            'Remote scope' => $posting->remote_scope ?? '—',
            'Status' => $posting->deleted_at !== null ? 'Suspended' : 'Active',
            'Score' => $latest !== null ? (string) $latest->total_score : '—',
            'Band' => $latest?->band ?? '—',
            'Cap' => $latest?->cap_reason ?? '—',
            'Channel' => $posting->discovery_channel ?? '—',
            'Created' => $posting->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  array{Title: string, Employer: string, Location: string, Remote scope: string, Status: string, Score: string, Band: string, Cap: string, Channel: string, Created: string}  $data
     * @return array{Title: string, Employer: string, Location: string, Remote scope: string, Status: string, Score: string, Band: string, Cap: string, Channel: string, Created: string}
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
     * @param  array{Title: string, Employer: string, Location: string, Remote scope: string, Status: string, Score: string, Band: string, Cap: string, Channel: string, Created: string}  $data
     * @return array{Title: string, Employer: string, Location: string, Remote scope: string, Status: string, Score: string, Band: string, Cap: string, Channel: string, Created: string}
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }
}
