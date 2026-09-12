<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

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
 */
final readonly class SpeechDisfluencyDetector
{
    /**
     * @param  list<string>  $fillerSounds  non-lexical hesitations ("eh", "mmm")
     * @param  list<string>  $fillerWords  real words used as padding ("este", "like")
     * @param  list<string>  $fillerPhrases  multi-word padding ("o sea", "you know")
     * @param  list<string>  $repetitionAllowList  words that legitimately double ("no no", "muy muy")
     */
    public function __construct(
        private array $fillerSounds,
        private array $fillerWords,
        private array $fillerPhrases,
        private array $repetitionAllowList,
        private int $maxStutterFragmentMs,
        private float $minConfidence,
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
            ...($this->wants(SpeechCategory::FillerWord, $enabled) ? $this->detectFillerWords($transcript) : []),
            ...($this->wants(SpeechCategory::Repetition, $enabled) ? $this->detectRepetitions($transcript) : []),
            ...($this->wants(SpeechCategory::Stutter, $enabled) ? $this->detectStutters($transcript) : []),
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
