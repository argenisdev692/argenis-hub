<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

/**
 * Parses FFmpeg `silencedetect` stderr into silent intervals (AD-7).
 * Adapted from GUIDE/VideoExport SilenceCutParser, now in milliseconds.
 */
final readonly class SilenceCutParser
{
    private const string START_PATTERN = '/silence_start:\s*(-?\d+(?:\.\d+)?)/';

    private const string END_PATTERN = '/silence_end:\s*(-?\d+(?:\.\d+)?)/';

    /**
     * @param  list<string>|string  $stderr  raw output, whole or split into lines
     * @return list<TimeRange>
     */
    #[\NoDiscard]
    public function parse(array|string $stderr, int $durationMs): array
    {
        $lines = is_array($stderr) ? $stderr : (preg_split('/\R/', $stderr) ?: []);
        $silences = [];
        $openStartMs = null;

        foreach ($lines as $line) {
            if (preg_match(self::START_PATTERN, $line, $start) === 1) {
                $openStartMs = max(0, self::toMilliseconds($start[1]));

                continue;
            }

            if ($openStartMs !== null && preg_match(self::END_PATTERN, $line, $end) === 1) {
                $endMs = min($durationMs, self::toMilliseconds($end[1]));

                if ($endMs > $openStartMs) {
                    $silences[] = new TimeRange($openStartMs, $endMs);
                }

                $openStartMs = null;
            }
        }

        // A recording that ends in silence never prints `silence_end`.
        if ($openStartMs !== null && $durationMs > $openStartMs) {
            $silences[] = new TimeRange($openStartMs, $durationMs);
        }

        return $silences;
    }

    private static function toMilliseconds(string $seconds): int
    {
        return (int) round((float) $seconds * 1000);
    }
}
