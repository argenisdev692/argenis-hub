<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Illuminate\Support\Str;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Enforces the owner's never-cut phrases on what the model proposed.
 *
 * "CONSERVAR: observemos, muy bien, vamos a enviar, enviemos" is a hard
 * constraint, not a preference, and the model cannot be trusted to honour it
 * every time — the same lesson `pause_marker` already taught: literal matching
 * is the code's job, judgement is the model's. A proposal whose words contain
 * one of these phrases is dropped whole.
 *
 * **Dropping, not trimming.** Shrinking the range around the phrase would
 * invent a cut nobody reviewed, at a boundary the model never reasoned about.
 * Losing a cut is recoverable; cutting through "muy bien" because the range
 * happened to start inside it is not. The phrase is reported back so the owner
 * can see what the request protected.
 *
 * Matching is accent-, case- and punctuation-insensitive and word-bounded:
 * "este" must not block "esteban", and "e" must not block every word that
 * contains an e. Both sides go through the same normaliser, so the phrase and
 * the transcript can never disagree about what a word is.
 */
final readonly class KeepPhraseGuard
{
    /**
     * @param  list<AiCutProposal>  $proposals
     * @param  list<string>  $phrases
     * @return array{0: list<AiCutProposal>, 1: list<string>} surviving proposals, phrases that blocked one
     */
    #[\NoDiscard]
    public function filter(array $proposals, Transcript $transcript, array $phrases): array
    {
        $guards = $this->guards($phrases);

        if ($guards === []) {
            return [$proposals, []];
        }

        $remaining = [];
        $blocked = [];

        foreach ($proposals as $proposal) {
            $needle = $this->matchingPhrase($this->cutText($proposal, $transcript), $guards);

            if ($needle === null) {
                $remaining[] = $proposal;

                continue;
            }

            $blocked[] = $needle;
        }

        return [$remaining, array_values(array_unique($blocked))];
    }

    /**
     * The same guard over decisions a producer already resolved to milliseconds.
     *
     * The AI half is filtered before it becomes a proposal; this half protects
     * the deterministic cuts, where a dictionary or a repetition rule can do
     * real damage: "enviemos, enviemos" is a repetition to the detector and a
     * phrase the lesson needs to the owner. A silence has no words in its range,
     * so it can only be blocked if the phrase truly overlaps it.
     *
     * @param  list<CutDecision>  $decisions
     * @param  list<string>  $phrases
     * @return array{0: list<CutDecision>, 1: list<string>} surviving decisions, phrases that blocked one
     */
    #[\NoDiscard]
    public function filterDecisions(array $decisions, Transcript $transcript, array $phrases): array
    {
        $guards = $this->guards($phrases);

        if ($guards === []) {
            return [$decisions, []];
        }

        $remaining = [];
        $blocked = [];

        foreach ($decisions as $decision) {
            $needle = $this->matchingPhrase($this->textFor($decision, $transcript), $guards);

            if ($needle === null) {
                $remaining[] = $decision;

                continue;
            }

            $blocked[] = $needle;
        }

        return [$remaining, array_values(array_unique($blocked))];
    }

    /**
     * @param  list<string>  $phrases
     * @return array<string, string> normalised phrase => the phrase as the owner wrote it
     */
    private function guards(array $phrases): array
    {
        $guards = [];

        foreach ($phrases as $phrase) {
            $normalised = self::normalise($phrase);

            if ($normalised !== '') {
                $guards[$normalised] = $phrase;
            }
        }

        return $guards;
    }

    /**
     * @param  array<string, string>  $guards
     */
    private function matchingPhrase(string $text, array $guards): ?string
    {
        $haystack = ' '.self::normalise($text).' ';

        foreach ($guards as $needle => $original) {
            if (str_contains($haystack, ' '.$needle.' ')) {
                return $original;
            }
        }

        return null;
    }

    private function cutText(AiCutProposal $proposal, Transcript $transcript): string
    {
        $words = [];

        for ($index = $proposal->startWordIndex; $index <= $proposal->endWordIndex; $index++) {
            $word = $transcript->words[$index] ?? null;

            if ($word instanceof TranscriptWord) {
                $words[] = $word->text;
            }
        }

        return implode(' ', $words);
    }

    /**
     * The words a time range covers, by overlap: a cut carries padding and a
     * silence carries none, and containment would miss both.
     */
    private function textFor(CutDecision $decision, Transcript $transcript): string
    {
        $words = [];

        foreach ($transcript->words as $word) {
            if ($word->endMs <= $decision->startMs || $word->startMs >= $decision->endMs) {
                continue;
            }

            $words[] = $word->text;
        }

        return implode(' ', $words);
    }

    /**
     * Lowercase, de-accented, punctuation collapsed to single spaces — so
     * "Muy bien." and "muy  bien" are one phrase.
     */
    private static function normalise(string $text): string
    {
        $ascii = Str::lower(Str::ascii($text));
        $spaced = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $ascii) ?? $ascii;

        return trim(preg_replace('/\s+/u', ' ', $spaced) ?? $spaced);
    }
}
