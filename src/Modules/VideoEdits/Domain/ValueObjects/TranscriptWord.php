<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * One spoken word with its own timing — the unit V2 detection actually cuts on.
 *
 * Segments are far too coarse for this job: Whisper segments run 5–30 seconds,
 * while the "eh" we need to remove lasts about 300 ms. Cutting on segments
 * would delete whole sentences, which is why the transcription adapter must
 * request word-level granularity.
 */
final readonly class TranscriptWord
{
    public function __construct(
        public string $text,
        public int $startMs,
        public int $endMs,
        public ?float $confidence = null,
    ) {
        if ($startMs < 0 || $endMs < $startMs) {
            throw new InvalidArgumentException('A transcript word must span a non-negative, ordered range.');
        }
    }

    public function durationMs(): int
    {
        return $this->endMs - $this->startMs;
    }

    public function toTimeRange(): TimeRange
    {
        return new TimeRange($this->startMs, $this->endMs);
    }

    /**
     * Comparison form: lower-cased, accent-folded and stripped of the
     * punctuation Whisper attaches ("Eh," and "eh" are the same hesitation).
     * Accents are folded so a transcript that writes "ehh" or "éh" still
     * matches a dictionary entry written plainly.
     */
    #[\NoDiscard]
    public function normalized(): string
    {
        $lowered = mb_strtolower(trim($this->text));
        $folded = strtr($lowered, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);

        return trim(preg_replace('/[^\p{L}\p{N}\-]+/u', '', $folded) ?? '');
    }

    /**
     * Whisper brackets non-verbal audio: "[laughs]", "(coughs)", "*sighs*", "♪".
     * These carry no lexical content, so they never survive `normalized()` —
     * they have to be recognised from the raw text.
     */
    #[\NoDiscard]
    public function isBracketedSound(): bool
    {
        $trimmed = trim($this->text);

        return preg_match('/^[\[\(\*♪♫][^\]\)\*]*[\]\)\*♪♫]?$/u', $trimmed) === 1
            && $trimmed !== '';
    }
}
