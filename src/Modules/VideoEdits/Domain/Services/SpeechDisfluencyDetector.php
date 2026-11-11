<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Domain\ValueObjects\SpeechDetection;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Finds the disfluencies in a transcript (US-10). Pure: a transcript in,
 * detections out — no FFmpeg, no HTTP, no database, so every rule below is
 * unit-tested against a hand-written transcript.
 *
 * It only ever PROPOSES. Everything it returns still goes through
 * {@see CutDecisionValidator} and {@see CutPlanner} exactly like a V1 manual
 * range does (EX-3), which is what stops a bad dictionary entry from silently
 * destroying a video: an out-of-range or overlapping detection is rejected and
 * reported, not applied.
 *
 * Detection is deliberately conservative. A missed filler costs the user one
 * manual edit; a wrongly removed real word costs them a re-record, so every
 * rule here prefers to skip a doubtful case.
 *
 * **What belongs here and what does not.** Everything in this class is a fact
 * about the audio that can be read off Whisper's own output — a token that is
 * in the dictionary, a word that repeats, a fragment the transcriber heard stop
 * mid-syllable. Judgement about what the speaker *meant* is not here, and
 * neither is a question only a model can answer: where a flagged mistake began,
 * or whether a passage is redundant. That division is what the AI decision
 * producer in the Application layer exists for, and it is why
 * {@see SpeechCategory::PauseMarker} now exists at all — a spoken "PAUSA" is a
 * literal token match and never needed a model.
 */
final readonly class SpeechDisfluencyDetector
{
    /**
     * Whisper sometimes aligns a marker to a point: `Pausa` comes back as
     * start == end, and the word after it carries the whole duration. Below this
     * span the marker has no usable audio of its own and has to be widened.
     */
    private const int MIN_PAUSE_MARKER_MS = 120;

    /**
     * How far before a point-aligned marker its audio may begin. A spoken
     * "pausa" is ~400–700 ms; 600 ms covers it without reaching into an earlier
     * sentence.
     */
    private const int PAUSE_MARKER_LEAD_MS = 600;

    /**
     * @param  list<string>  $fillerSounds  non-lexical hesitations ("eh", "mmm")
     * @param  list<string>  $fillerWords  real words used as padding ("este", "like")
     * @param  list<string>  $fillerPhrases  multi-word padding ("o sea", "you know")
     * @param  list<string>  $repetitionAllowList  words that legitimately double ("no no", "muy muy")
     * @param  list<string>  $pausePhrases  spoken mistake markers ("pausa", "otra vez")
     * @param  list<string>  $elongationPatterns  PCRE for held vowels ("eee", "deee", "queee")
     */
    public function __construct(
        private array $fillerSounds,
        private array $fillerWords,
        private array $fillerPhrases,
        private array $repetitionAllowList,
        private int $maxStutterFragmentMs,
        private float $minConfidence,
        private array $pausePhrases = [],
        private array $elongationPatterns = [],
        private int $roboticPauseTargetMs = 150,
        private int $roboticPauseMaxMs = 1_000,
    ) {}

    /**
     * @param  list<SpeechCategory>  $enabledCategories
     * @return list<SpeechDetection>
     */
    #[\NoDiscard]
    public function detect(Transcript $transcript, array $enabledCategories): array
    {
        if ($transcript->isEmpty() || $enabledCategories === []) {
            return [];
        }

        $enabled = array_column(
            array_map(static fn (SpeechCategory $category): array => [$category->value], $enabledCategories),
            0,
        );

        $detections = [
            ...($this->wants(SpeechCategory::VocalSound, $enabled) ? $this->detectVocalSounds($transcript) : []),
            ...($this->wants(SpeechCategory::Filler, $enabled) ? $this->detectDictionary($transcript, $this->fillerSounds, SpeechCategory::Filler) : []),
            // Under Filler, not its own category: an elongated vowel IS a
            // non-lexical hesitation, and it is spelled differently every time,
            // so a category of its own would promise a switch that changes
            // nothing the user can see.
            ...($this->wants(SpeechCategory::Filler, $enabled) ? $this->detectElongations($transcript) : []),
            ...($this->wants(SpeechCategory::FillerWord, $enabled) ? $this->detectFillerWords($transcript) : []),
            ...($this->wants(SpeechCategory::Repetition, $enabled) ? $this->detectRepetitions($transcript) : []),
            ...($this->wants(SpeechCategory::Stutter, $enabled) ? $this->detectStutters($transcript) : []),
            ...($this->wants(SpeechCategory::PauseMarker, $enabled) ? $this->detectPauseMarkers($transcript) : []),
            ...($this->wants(SpeechCategory::RoboticPause, $enabled) ? $this->detectRoboticPauses($transcript) : []),
        ];

        usort($detections, static fn (SpeechDetection $a, SpeechDetection $b): int => $a->startMs <=> $b->startMs);

        return array_values($detections);
    }

    /**
     * @param  list<string>  $enabled
     */
    private function wants(SpeechCategory $category, array $enabled): bool
    {
        return in_array($category->value, $enabled, true);
    }

    /**
     * @return list<SpeechDetection>
     */
    private function detectVocalSounds(Transcript $transcript): array
    {
        $detections = [];

        foreach ($transcript->words as $word) {
            if ($word->isBracketedSound()) {
                $detections[] = $this->detection($word, SpeechCategory::VocalSound, $word->text);
            }
        }

        return $detections;
    }

    /**
     * @param  list<string>  $dictionary
     * @return list<SpeechDetection>
     */
    private function detectDictionary(Transcript $transcript, array $dictionary, SpeechCategory $category): array
    {
        $detections = [];

        foreach ($transcript->words as $word) {
            if ($this->isLowConfidence($word)) {
                continue;
            }

            $normalized = $word->normalized();

            if ($normalized !== '' && in_array($normalized, $dictionary, true)) {
                $detections[] = $this->detection($word, $category, $normalized);
            }
        }

        return $detections;
    }

    /**
     * Single-word padding plus the multi-word phrases that only read as padding
     * together — "o sea" must go as a unit, while the word "sea" on its own is
     * ordinary Spanish and must never be touched.
     *
     * @return list<SpeechDetection>
     */
    private function detectFillerWords(Transcript $transcript): array
    {
        $detections = $this->detectDictionary($transcript, $this->fillerWords, SpeechCategory::FillerWord);
        $words = $transcript->words;
        $wordCount = count($words);

        foreach ($this->fillerPhrases as $phrase) {
            $tokens = array_values(array_filter(explode(' ', $phrase)));
            $length = count($tokens);

            if ($length < 2) {
                continue;
            }

            for ($index = 0; $index + $length <= $wordCount; $index++) {
                $window = array_slice($words, $index, $length);

                if ($this->windowMatches($window, $tokens)) {
                    $first = array_first($window);
                    $last = array_last($window);

                    $detections[] = new SpeechDetection(
                        category: SpeechCategory::FillerWord,
                        startMs: $first->startMs,
                        endMs: $last->endMs,
                        confidence: $this->averageConfidence($window),
                        evidence: ['text' => $phrase, 'detector' => 'phrase'],
                    );
                }
            }
        }

        return $detections;
    }

    /**
     * A held vowel: "eeee", "y deee", "para queee".
     *
     * This could not be a dictionary. Whisper spells the same hesitation
     * differently every time — "eee", "eeee", "eeeee" — so the literal list it
     * would have to match against is unbounded, and the one hand-written entry
     * that did exist ("esteee") was right for exactly one recording.
     *
     * The patterns are config, and each one is narrow enough to exclude a real
     * word, because that is the failure this module refuses: a 3-repeat floor
     * keeps "see"/"coffee" safe while catching "deee", and the bare-run pattern
     * requires one letter repeated rather than a character class, or it would
     * match "ana".
     *
     * Categorised as Filler, so it lands on {@see CutReason::Filler} and shares
     * the switch a user already has for "eh".
     *
     * @return list<SpeechDetection>
     */
    private function detectElongations(Transcript $transcript): array
    {
        if ($this->elongationPatterns === []) {
            return [];
        }

        $detections = [];

        foreach ($transcript->words as $word) {
            if ($this->isLowConfidence($word) || $word->isBracketedSound()) {
                continue;
            }

            $normalized = $word->normalized();

            if ($normalized === '') {
                continue;
            }

            // The literal dictionary owns the spellings it enumerates. Without
            // this, a word listed as a filler sounds ("mmm") is also a bare run
            // of one letter repeated three times, so the two rules both claim it
            // and it is reported — and later cut — twice.
            if (in_array($normalized, $this->fillerSounds, true)) {
                continue;
            }

            foreach ($this->elongationPatterns as $pattern) {
                if (preg_match($pattern, $normalized) === 1) {
                    $detections[] = $this->detection($word, SpeechCategory::Filler, $normalized);

                    continue 2;
                }
            }
        }

        return $detections;
    }

    /**
     * A mistake the speaker flagged out loud: "pausa", "pausa acá", "otra vez".
     *
     * **Only the marker is cut here.** The failed take the speaker was
     * correcting has no exact boundary — where it began is a judgement about
     * intent, which is what {@see CutReason::Retake} and the AI analyzer are
     * for. Removing the marker deterministically and letting the model propose
     * the wider span is the split that keeps this rule safe: this half is a
     * literal match that cannot be wrong, that half is reviewed by a person.
     *
     * Widening past the marker here would be actively harmful. At 02:56 in the
     * reference recording the speaker says "Contestamos… pausa acá…" and then
     * restarts; cutting back on a guess swallows the opening word of the
     * *correct* sentence along with the wrong one.
     *
     * The dictionary is deliberately short — see
     * `video-edit.speech.dictionaries.pause_phrases`. "para", "otra" and
     * "repetir" are all real Spanish and were all rejected for that reason.
     *
     * @return list<SpeechDetection>
     */
    private function detectPauseMarkers(Transcript $transcript): array
    {
        if ($this->pausePhrases === []) {
            return [];
        }

        // Same window machinery as the filler phrases: "pausa acá" has to go as a
        // unit, and a single-word "pausa" beside it must not also be cut.
        $detections = [];
        $words = $transcript->words;
        $wordCount = count($words);

        foreach ($this->pausePhrases as $phrase) {
            $tokens = array_values(array_filter(explode(' ', $phrase)));
            $length = count($tokens);

            if ($length < 1 || $length > $wordCount) {
                continue;
            }

            for ($index = 0; $index + $length <= $wordCount; $index++) {
                $window = array_slice($words, $index, $length);

                if (! $this->windowMatches($window, $tokens)) {
                    continue;
                }

                [$startMs, $endMs] = $this->pauseMarkerSpan($window, $index, $words);

                // A point marker with no room before it has no audio to remove.
                // Cutting anyway would take the next word's attack instead.
                if ($endMs <= $startMs) {
                    continue;
                }

                $detections[] = new SpeechDetection(
                    category: SpeechCategory::PauseMarker,
                    startMs: $startMs,
                    endMs: $endMs,
                    confidence: $this->averageConfidence($window),
                    evidence: ['text' => $phrase, 'detector' => 'pause_marker'],
                );

                // Overlap inside one phrase is settled here, so "pausa" is not
                // also claimed a second time by the "pausa aca" that contains it.
                $index += $length - 1;
            }
        }

        return $this->longestSpansOnly($detections);
    }

    /**
     * The audio a marker actually occupies, not the point Whisper aligned it to.
     *
     * The transcript this module runs on is full of `Pausa` words with
     * `start_ms == end_ms`, immediately followed by `acá` with the full
     * duration. The marker's audio is **before** that point, so a cut that
     * starts there removes the "acá" and leaves the spoken "pausa" — the
     * asymmetry that made some markers disappear and others survive.
     *
     * When the first token has no measurable span, the cut starts at the
     * previous word's end if that is within {@see self::PAUSE_MARKER_LEAD_MS},
     * or at the bounded lead before the alignment point otherwise. It never
     * moves forward, so it cannot reach into the next word.
     *
     * @param  list<TranscriptWord>  $window
     * @param  list<TranscriptWord>  $words
     * @return array{0: int, 1: int}
     */
    private function pauseMarkerSpan(array $window, int $index, array $words): array
    {
        $first = $window[0];
        $last = array_last($window);
        $startMs = $first->startMs;
        $endMs = $last->endMs;

        if ($first->durationMs() < self::MIN_PAUSE_MARKER_MS) {
            $previousEnd = $index > 0
                ? $words[$index - 1]->endMs
                : $startMs - self::PAUSE_MARKER_LEAD_MS;

            $startMs = min($startMs, max($previousEnd, $startMs - self::PAUSE_MARKER_LEAD_MS));
        }

        return [$startMs, $endMs];
    }

    /**
     * Keeps a detection only when no other one contains it, so the LONGEST
     * dictionary entry at a position wins.
     *
     * Without this, "pausa acá" is reported twice — once by its own two-word
     * entry and once by the one-word "pausa" starting on the same word — and the
     * planner merges two decisions into a single cut that then claims two
     * reasons for one span. Two genuinely separate markers ("pausa pausa") do not
     * contain one another, so both survive.
     *
     * @param  list<SpeechDetection>  $detections
     * @return list<SpeechDetection>
     */
    private function longestSpansOnly(array $detections): array
    {
        return array_values(array_filter(
            $detections,
            static fn (SpeechDetection $detection): bool => ! array_any(
                $detections,
                static fn (SpeechDetection $other): bool => $other !== $detection
                    && $other->startMs <= $detection->startMs
                    && $other->endMs >= $detection->endMs
                    && ($other->startMs < $detection->startMs || $other->endMs > $detection->endMs),
            ),
        ));
    }

    /**
     * @param  list<TranscriptWord>  $window
     * @param  list<string>  $tokens
     */
    private function windowMatches(array $window, array $tokens): bool
    {
        foreach ($tokens as $position => $token) {
            if ($window[$position]->normalized() !== $token) {
                return false;
            }
        }

        return true;
    }

    /**
     * The same word twice in a row: the FIRST occurrence is cut and the second
     * kept, because the second is the one the speaker carried on from — cutting
     * the second would splice the audio mid-thought.
     *
     * @return list<SpeechDetection>
     */
    private function detectRepetitions(Transcript $transcript): array
    {
        $detections = [];
        $words = $transcript->words;

        for ($index = 0; $index < count($words) - 1; $index++) {
            $current = $words[$index];
            $next = $words[$index + 1];
            $normalized = $current->normalized();

            if ($normalized === '' || $normalized !== $next->normalized()) {
                continue;
            }

            // "no no" and "muy muy" are emphasis in Spanish, not a stumble.
            if (in_array($normalized, $this->repetitionAllowList, true)) {
                continue;
            }

            if ($this->isLowConfidence($current)) {
                continue;
            }

            $detections[] = $this->detection($current, SpeechCategory::Repetition, $normalized);
        }

        return $detections;
    }

    /**
     * A false start: a short fragment immediately followed by a longer word it
     * is the beginning of — "pe-" then "pero". Both the length ceiling and the
     * strict-prefix test matter; without them "por" before "porque" (a real
     * phrase) would be eaten.
     *
     * @return list<SpeechDetection>
     */
    private function detectStutters(Transcript $transcript): array
    {
        $detections = [];
        $words = $transcript->words;

        for ($index = 0; $index < count($words) - 1; $index++) {
            $fragment = $words[$index];
            $complete = $words[$index + 1];
            $fragmentText = $fragment->normalized();
            $completeText = $complete->normalized();

            if ($fragmentText === '' || $fragmentText === $completeText) {
                continue;
            }

            $hyphenated = str_ends_with(trim($fragment->text), '-');
            $isPrefix = str_starts_with($completeText, rtrim($fragmentText, '-'));
            $shortEnough = $fragment->durationMs() <= $this->maxStutterFragmentMs;

            // A hyphen is the transcriber telling us it was cut off, so it
            // stands on its own; without one we need the duration evidence too.
            if ($isPrefix && ($hyphenated || $shortEnough)) {
                $detections[] = $this->detection($fragment, SpeechCategory::Stutter, $fragment->text);
            }
        }

        return $detections;
    }

    /**
     * Closes only the excess air between two words in the same phrase. The cut
     * keeps the configured share on both sides; the renderer's join air makes
     * up the rest of the total 150 ms budget supplied by the composition root.
     *
     * Ellipses do not close a phrase here: "vamos... a... ingresar" is exactly
     * the robotic delivery this rule repairs. Terminal . ? ! : ; and uncertain
     * word timings stay untouched, as does the generation wait after a prompt.
     *
     * @return list<SpeechDetection>
     */
    private function detectRoboticPauses(Transcript $transcript): array
    {
        $detections = [];
        $words = $transcript->words;

        for ($index = 1, $wordCount = count($words); $index < $wordCount; $index++) {
            $previous = $words[$index - 1];
            $word = $words[$index];
            $gapMs = $word->startMs - $previous->endMs;

            if ($gapMs <= $this->roboticPauseTargetMs || $gapMs > $this->roboticPauseMaxMs
                || $previous->isBracketedSound() || $word->isBracketedSound()
                || $this->isLowConfidence($previous) || $this->isLowConfidence($word)
                || $this->endsNarrativeSentence($previous->text)
                || $this->isGenerationWait($words, $index - 1)) {
                continue;
            }

            $keepBeforeMs = intdiv($this->roboticPauseTargetMs, 2);
            $keepAfterMs = $this->roboticPauseTargetMs - $keepBeforeMs;

            $detections[] = new SpeechDetection(
                category: SpeechCategory::RoboticPause,
                startMs: $previous->endMs + $keepBeforeMs,
                endMs: $word->startMs - $keepAfterMs,
                confidence: $this->averageConfidence([$previous, $word]),
                evidence: [
                    'detector' => 'robotic_pause',
                    'gap_ms' => $gapMs,
                    'target_gap_ms' => $this->roboticPauseTargetMs,
                ],
            );
        }

        return $detections;
    }

    /**
     * A full stop is a sentence boundary the speaker already made; an ellipsis
     * is not. "vamos... a... ingresar" is one phrase delivered robotically, and
     * treating "..." as terminal would skip exactly the case this rule exists
     * for. Only . ? ! : ; close a phrase here.
     */
    private function endsNarrativeSentence(string $text): bool
    {
        $trimmed = trim($text);

        if (preg_match('/(?:\.\.\.|…)["»”\')\]]*$/u', $trimmed) === 1) {
            return false;
        }

        return preg_match('/[.?!:;]["»”\')\]]*$/u', $trimmed) === 1;
    }

    /**
     * @param  list<TranscriptWord>  $words
     */
    private function isGenerationWait(array $words, int $previousIndex): bool
    {
        $tokens = [];

        for ($index = max(0, $previousIndex - 2); $index <= $previousIndex; $index++) {
            $tokens[] = $words[$index]->normalized();
        }

        return in_array(implode(' ', array_slice($tokens, -3)), [
            'enviar el prompt',
            'enviemos el prompt',
        ], true);
    }

    private function detection(TranscriptWord $word, SpeechCategory $category, string $evidence): SpeechDetection
    {
        return new SpeechDetection(
            category: $category,
            startMs: $word->startMs,
            endMs: $word->endMs,
            confidence: $word->confidence,
            evidence: ['text' => $evidence],
        );
    }

    /**
     * A word the provider itself is unsure about is a word we must not cut on.
     */
    private function isLowConfidence(TranscriptWord $word): bool
    {
        return $word->confidence !== null && $word->confidence < $this->minConfidence;
    }

    /**
     * @param  list<TranscriptWord>  $words
     */
    private function averageConfidence(array $words): ?float
    {
        $scores = array_values(array_filter(
            array_map(static fn (TranscriptWord $word): ?float => $word->confidence, $words),
            static fn (?float $score): bool => $score !== null,
        ));

        return $scores === [] ? null : round(array_sum($scores) / count($scores), 4);
    }
}
