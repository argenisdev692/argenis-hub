<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * The speech-detection categories a user can switch on individually (US-10).
 *
 * A category is not the same thing as a {@see CutReason}: the category is what
 * the user chooses in the request, the reason is what gets persisted on the
 * decision. They map 1:1 today, and keeping them separate is what lets V3 add
 * reasons (`pause_marker`, `retake`) that are not user-selectable categories.
 *
 * Resolves R1 from clarify.md — "vocal sound" is the non-verbal audio Whisper
 * brackets in its transcript (laughter, coughs, applause), which is distinct
 * from a filler ("mmm") because a filler is transcribed as a word.
 */
enum SpeechCategory: string
{
    /** Non-lexical hesitation sounds transcribed as words: "eh", "mmm", "ah". */
    case Filler = 'filler';

    /** Real words used as verbal padding: "este", "o sea", "like", "you know". */
    case FillerWord = 'filler_word';

    /** A false start cut short by the next attempt: "p-", "pe-" before "pero". */
    case Stutter = 'stutter';

    /** The same word said twice in a row: "the the", "y y". */
    case Repetition = 'repetition';

    /** Non-verbal audio Whisper brackets: "[laughs]", "(coughs)", "♪". */
    case VocalSound = 'vocal_sound';

    public function toCutReason(): CutReason
    {
        return match ($this) {
            self::Filler => CutReason::Filler,
            self::FillerWord => CutReason::FillerWord,
            self::Stutter => CutReason::Stutter,
            self::Repetition => CutReason::Repetition,
            self::VocalSound => CutReason::VocalSound,
        };
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The default when a request enables speech cleanup without naming
     * categories: every category, which is what "automatic editing" means.
     *
     * @return list<self>
     */
    #[\NoDiscard]
    public static function all(): array
    {
        return self::cases();
    }
}
