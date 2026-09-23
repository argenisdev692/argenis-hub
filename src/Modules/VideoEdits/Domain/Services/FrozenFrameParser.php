<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

/**
 * Parses FFmpeg `freezedetect` stderr into still intervals.
 *
 * The filter logs `lavfi.freezedetect.freeze_start: 12.3` and later
 * `lavfi.freezedetect.freeze_end: 15.8`; a recording that ends frozen never
 * prints the end, exactly like `silencedetect` (see {@see SilenceCutParser}).
 */
final readonly class FrozenFrameParser
{
    private const string START_PATTERN = '/freeze_start:\s*(-?\d+(?:\.\d+)?)/';

    private const string END_PATTERN = '/freeze_end:\s*(-?\d+(?:\.\d+)?)/';

    /**
     * @param  list<string>|string  $stderr  raw output, whole or split into lines
     * @return list<TimeRange>
     */
    #[\NoDiscard]
    public function parse(array|string $stderr, int $durationMs): array
    {
        $lines = is_array($stderr) ? $stderr : (preg_split('/\R/', $stderr) ?: []);
        $frozen = [];
        $openStartMs = null;

        foreach ($lines as $line) {
            if (preg_match(self::START_PATTERN, $line, $start) === 1) {
                $openStartMs = max(0, self::toMilliseconds($start[1]));

                continue;
            }

            if ($openStartMs !== null && preg_match(self::END_PATTERN, $line, $end) === 1) {
                $endMs = min($durationMs, self::toMilliseconds($end[1]));

                if ($endMs > $openStartMs) {
                    $frozen[] = new TimeRange($openStartMs, $endMs);
                }

                $openStartMs = null;
            }
        }

        if ($openStartMs !== null && $durationMs > $openStartMs) {
            $frozen[] = new TimeRange($openStartMs, $durationMs);
        }

        return $frozen;
    }

    /**
     * Share of `$range` during which the screen was moving: 0 when it stood
     * still throughout, 1 when it never did.
     *
     * @param  list<TimeRange>  $frozen
     */
    public static function activityRatio(TimeRange $range, array $frozen): float
    {
        $durationMs = $range->durationMs();

        if ($durationMs <= 0) {
            return 0.0;
        }

        $frozenMs = 0;

        foreach ($frozen as $still) {
            $frozenMs += max(0, min($range->endMs, $still->endMs) - max($range->startMs, $still->startMs));
        }

        return round(1 - min($durationMs, $frozenMs) / $durationMs, 2);
    }

    private static function toMilliseconds(string $seconds): int
    {
        return (int) round((float) $seconds * 1000);
    }
}
