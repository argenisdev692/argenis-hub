<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Support;

use Illuminate\Support\Carbon;
use Modules\VideoEdits\Application\DTOs\VideoEditFilterData;

/**
 * Turns the filters an export was run with into the one line that belongs under
 * the report's heading.
 *
 * A printed edit report is read away from the screen that produced it, and
 * "Video edits" alone cannot tell a reader whether they are holding every edit
 * or only March's failures. Stating the criteria on the page is the difference
 * between a report and a pile of rows.
 *
 * Pure and static: it reads a DTO and returns a string, so it is unit-tested
 * without a request, a database or a rendered PDF.
 */
final readonly class VideoEditFilterSummary
{
    /** Shown when no filter narrowed the export at all. */
    private const string UNFILTERED = 'Every edit in your history.';

    #[\NoDiscard]
    public static function describe(VideoEditFilterData $filters): string
    {
        $parts = array_values(array_filter([
            self::describeStatus($filters),
            self::describeMode($filters),
            self::describeDateRange($filters->dateFrom, $filters->dateTo),
            self::describeSearch($filters->search),
        ]));

        if ($parts === []) {
            return self::UNFILTERED;
        }

        return ucfirst(implode(' · ', $parts)).'.';
    }

    private static function describeStatus(VideoEditFilterData $filters): ?string
    {
        return $filters->status === null ? null : "{$filters->status->value} only";
    }

    private static function describeMode(VideoEditFilterData $filters): ?string
    {
        return $filters->mode === null
            ? null
            : str_replace('_', ' ', $filters->mode->value).' mode';
    }

    /**
     * Dates are re-formatted rather than echoed: the filter carries ISO
     * `YYYY-MM-DD`, and a report that says "March 1, 2026" beside a column of
     * dates in the same format reads as one document.
     */
    private static function describeDateRange(?string $from, ?string $to): ?string
    {
        $start = self::formatDate($from);
        $end = self::formatDate($to);

        return match (true) {
            $start !== null && $end !== null => "created {$start} – {$end}",
            $start !== null => "created from {$start}",
            $end !== null => "created up to {$end}",
            default => null,
        };
    }

    private static function formatDate(?string $iso): ?string
    {
        if ($iso === null || trim($iso) === '') {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('F j, Y');
        } catch (\Throwable) {
            // An unparseable bound is not worth failing an export over — the
            // rows are already correct, so the line simply omits it.
            return null;
        }
    }

    private static function describeSearch(?string $search): ?string
    {
        $term = trim((string) $search);

        return $term === '' ? null : "reference starting “{$term}”";
    }
}
