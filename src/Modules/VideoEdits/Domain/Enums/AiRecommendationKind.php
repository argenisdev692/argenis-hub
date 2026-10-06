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
 *
 * The last two exist because the round-2 supervisor review named two faults the
 * original five kinds could not describe, and both need the recording fixed at
 * the desk rather than in the timeline. `RushedStep` and `UnclearWord` are
 * measured from Whisper's own word timings, so their numbers are exact.
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
     * A script PROMPT read aloud word for word instead of pasted and summarised
     * in one sentence. Fixing it needs a re-record, so it is advice, not a cut.
     */
    case ReadPromptAloud = 'read_prompt_aloud';

    /**
     * A stretch of the example walked through markedly faster than the speaker's
     * own average for the recording, where a viewer loses the step. "Ciertos pasos
     * de los ejemplos se muestran muy rápido y el alumno puede perderse".
     *
     * Carries a time range: knowing it happened is useless without knowing where.
     * A re-record can be aimed at those seconds.
     */
    case RushedStep = 'rushed_step';

    /**
     * Words Whisper heard stop mid-syllable, which is the transcriber's evidence
     * that the microphone threshold cut them off. Normalising the output rescues
     * the words that survived; this names the ones that did not, because raising
     * the preamp gain is a desk change and no timeline can do it.
     */
    case UnclearWord = 'unclear_word';

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
