<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * The advisory half of an AI edit (US-14) — everything the model noticed that a
 * machine must NOT act on by itself (decision R6/R7).
 *
 * `Reduce` is the clearest case: "this explanation could be 30 seconds" has no
 * exact cut boundary, because saying it more briefly requires re-recording, not
 * deleting. These reach the user as a report and a PDF; they never become cuts.
 */
enum AiRecommendationKind: string
{
    /** "REDUCIR" — a rambling passage that could be shorter. */
    case Reduce = 'reduce';

    /** Content that departs from the script. */
    case OffScript = 'off_script';

    /** A script topic that was covered thinly, or not at all. */
    case ScriptCoverage = 'script_coverage';

    /** A section running long or short against its scripted time budget. */
    case Pacing = 'pacing';

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
