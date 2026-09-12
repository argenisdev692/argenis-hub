<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Http\Export;

use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;

/**
 * Maps one edit to its CSV / XLSX / PDF columns (BACKEND-PHP §8).
 *
 * **Documented deviation from the Status rule.** The rule's `Active`/`Suspended`
 * pair reports soft-delete state; this module hard-deletes by design (spec Q2/Q5,
 * plan AD-9) and has no `deleted_at`, so that column would be the constant
 * "Active" on every row. `Status` therefore carries the lifecycle state the
 * report is actually about — queued, processing, completed, failed — which is
 * the "business lifecycle state" the same rule keeps in its own column.
 *
 * Durations are stored in milliseconds and read by a human, so they are
 * formatted here rather than in the Blade view: the CSV needs the same string.
 */
final readonly class VideoEditExportTransformer
{
    /**
     * @return array{Reference: string, Mode: string, Status: string, Clips: string, Original: string, Final: string, Removed: string, Cuts: string, Created: string, Completed: string}
     */
    #[\NoDiscard]
    public static function transformForExcel(VideoEditEloquentModel $edit): array
    {
        return $edit
            |> self::extractBaseData(...)
            |> self::formatDates(...)
            |> self::sanitizeOutput(...);
    }

    /**
     * @return array{Reference: string, Mode: string, Status: string, Clips: string, Original: string, Final: string, Removed: string, Cuts: string, Created: string, Completed: string}
     */
    #[\NoDiscard]
    public static function transformForPdf(VideoEditEloquentModel $edit): array
    {
        return self::transformForExcel($edit);
    }

    /**
     * @return array{Reference: string, Mode: string, Status: string, Clips: string, Original: string, Final: string, Removed: string, Cuts: string, Created: string, Completed: string}
     */
    private static function extractBaseData(VideoEditEloquentModel $edit): array
    {
        return [
            'Reference' => $edit->uuid,
            'Mode' => self::label($edit->mode->value),
            'Status' => self::label($edit->status->value),
            'Clips' => (string) ($edit->sources_count ?? 0),
            'Original' => self::duration($edit->original_duration_ms),
            'Final' => self::duration($edit->final_duration_ms),
            'Removed' => self::duration($edit->removed_duration_ms),
            'Cuts' => (string) $edit->applied_cut_count,
            'Created' => $edit->created_at?->toIso8601String() ?? '',
            'Completed' => $edit->completed_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  array{Reference: string, Mode: string, Status: string, Clips: string, Original: string, Final: string, Removed: string, Cuts: string, Created: string, Completed: string}  $data
     * @return array{Reference: string, Mode: string, Status: string, Clips: string, Original: string, Final: string, Removed: string, Cuts: string, Created: string, Completed: string}
     */
    private static function formatDates(array $data): array
    {
        foreach (['Created', 'Completed'] as $key) {
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
     * @param  array{Reference: string, Mode: string, Status: string, Clips: string, Original: string, Final: string, Removed: string, Cuts: string, Created: string, Completed: string}  $data
     * @return array{Reference: string, Mode: string, Status: string, Clips: string, Original: string, Final: string, Removed: string, Cuts: string, Created: string, Completed: string}
     */
    private static function sanitizeOutput(array $data): array
    {
        return array_map(
            static fn (string $value): string => $value === '' ? '—' : $value,
            $data,
        );
    }

    /**
     * An edit that never ran has no duration to report; an em dash says that,
     * where "00:00" would read as a zero-length video.
     */
    private static function duration(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return '';
        }

        $totalSeconds = intdiv($milliseconds, 1_000);
        $hours = intdiv($totalSeconds, 3_600);
        $minutes = intdiv($totalSeconds % 3_600, 60);
        $seconds = $totalSeconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * `auto_edit` → `Auto edit`. The enum values are the wire contract; a
     * report is read by a person.
     */
    private static function label(string $enumValue): string
    {
        return ucfirst(str_replace('_', ' ', $enumValue));
    }
}
