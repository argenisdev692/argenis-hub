<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Export;

use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;

/**
 * Maps one course to its CSV / XLSX / PDF columns (BACKEND-PHP §8).
 *
 * `Status` is the soft-delete state (`Active` / `Suspended`, from `deleted_at`
 * only); the generation lifecycle stays in its own `Generation Status` column,
 * as the rule requires. Notes, the bible and file contents never leave the
 * backend through a report (FR-55) — only list metadata.
 */
final readonly class CourseExportTransformer
{
    /**
     * @return array{Reference: string, Title: string, Language: string, 'Generation Status': string, Videos: string, Generated: string, Status: string, Created: string, Updated: string}
     */
    #[\NoDiscard]
    public static function transformForExcel(CourseEloquentModel $course): array
    {
        return $course
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{Reference: string, Title: string, Language: string, 'Generation Status': string, Videos: string, Generated: string, Status: string, Created: string, Updated: string}
     */
    #[\NoDiscard]
    public static function transformForPdf(CourseEloquentModel $course): array
    {
        return self::transformForExcel($course);
    }

    /**
     * @return array{Reference: string, Title: string, Language: string, 'Generation Status': string, Videos: string, Generated: string, Status: string, Created: string, Updated: string}
     */
    private static function extractBaseData(CourseEloquentModel $course): array
    {
        return [
            'Reference' => $course->uuid,
            'Title' => $course->title,
            'Language' => strtoupper($course->language),
            'Generation Status' => ucfirst(str_replace('_', ' ', $course->status->value)),
            'Videos' => (string) ($course->getAttribute('videos_count') ?? 0),
            'Generated' => (string) ($course->getAttribute('generated_videos_count') ?? 0),
            'Status' => $course->deleted_at === null ? 'Active' : 'Suspended',
            'Created' => $course->created_at?->toIso8601String() ?? '',
            'Updated' => $course->updated_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  array{Reference: string, Title: string, Language: string, 'Generation Status': string, Videos: string, Generated: string, Status: string, Created: string, Updated: string}  $data
     * @return array{Reference: string, Title: string, Language: string, 'Generation Status': string, Videos: string, Generated: string, Status: string, Created: string, Updated: string}
     */
    private static function formatDates(array $data): array
    {
        foreach (['Created', 'Updated'] as $key) {
            if ($data[$key] === '') {
                continue;
            }

            try {
                $data[$key] = (new \DateTimeImmutable($data[$key]))->format('F j, Y');
            } catch (\Exception) {
                // Keep original value if parsing fails.
            }
        }

        return $data;
    }

    /**
     * @param  array{Reference: string, Title: string, Language: string, 'Generation Status': string, Videos: string, Generated: string, Status: string, Created: string, Updated: string}  $data
     * @return array{Reference: string, Title: string, Language: string, 'Generation Status': string, Videos: string, Generated: string, Status: string, Created: string, Updated: string}
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }
}
