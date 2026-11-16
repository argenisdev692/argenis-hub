<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline;

use Illuminate\Support\Str;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;

/**
 * Turns everything the producers proposed into the one list the owner reviews.
 *
 * Before this existed, only the AI's cuts reached the review and the
 * deterministic ones were applied silently — which meant the only cuts a person
 * could veto were the ones a model guessed, while the ones a dictionary or a
 * threshold guessed were untouchable. The table now shows every cut with its
 * start, end, reason and what survives, in the order they will be removed.
 *
 * The AI rows are passed through untouched: they already carry the model's
 * explanation, the transcript text and the corrected sentence (`keep_text`), and
 * their ids must not rotate or an owner's open review would lose its ticks.
 * Only the automatic decisions are built here, from the same transcript the
 * detector measured.
 */
final readonly class CutReviewBuilder
{
    public function __construct(
        private int $contextWords = 8,
    ) {}

    /**
     * @param  list<CutDecision>  $automatic  silence, manual ranges and speech cleanup
     */
    #[\NoDiscard]
    public function build(?AiCutReview $aiReview, array $automatic, Transcript $transcript): AiCutReview
    {
        $cuts = [...($aiReview?->cuts ?? [])];

        foreach ($automatic as $decision) {
            $cuts[] = $this->cut($decision, $transcript);
        }

        return AiCutReview::pendingAll($cuts);
    }

    private function cut(CutDecision $decision, Transcript $transcript): AiReviewableCut
    {
        [$first, $last] = $this->overlappingWords($decision, $transcript);

        $contextBefore = $this->words($transcript, max(0, $first - $this->contextWords), $first - max(0, $first - $this->contextWords));
        $contextAfter = $this->words($transcript, $last + 1, $this->contextWords);
        $text = $first <= $last ? $this->words($transcript, $first, $last - $first + 1) : '';

        // What survives around the cut. For a silence there are no words in the
        // range and the two neighbours are the sentence being stitched, which is
        // exactly what the owner needs to see.
        $keepText = trim($contextBefore.' '.$contextAfter);

        return new AiReviewableCut(
            id: (string) Str::uuid7(),
            reason: $decision->reason,
            startMs: $decision->startMs,
            endMs: $decision->endMs,
            confidence: $decision->confidence ?? 1.0,
            text: $text,
            contextBefore: $contextBefore,
            contextAfter: $contextAfter,
            explanation: self::motivo($decision),
            preselected: true,
            origin: $decision->origin,
            producer: $decision->producer,
            keepText: $keepText === '' ? null : $keepText,
        );
    }

    /**
     * The words the cut actually covers, by overlap rather than containment:
     * silence cuts carry padding and would otherwise report no text at all.
     *
     * @return array{0: int, 1: int} first and last index; first > last when empty
     */
    private function overlappingWords(CutDecision $decision, Transcript $transcript): array
    {
        $first = null;
        $last = null;

        foreach ($transcript->words as $index => $word) {
            if ($word->endMs <= $decision->startMs || $word->startMs >= $decision->endMs) {
                continue;
            }

            $first ??= $index;
            $last = $index;
        }

        return $first === null ? [0, -1] : [$first, $last];
    }

    private function words(Transcript $transcript, int $offset, int $length): string
    {
        if ($length <= 0 || $offset < 0) {
            return '';
        }

        return implode(' ', array_map(
            static fn ($word): string => $word->text,
            array_slice($transcript->words, $offset, $length),
        ));
    }

    /**
     * Why the cut is there, in the owner's language: the dictionary reasons are
     * named plainly, and a robotic gap reports the target it was closed to.
     */
    private static function motivo(CutDecision $decision): string
    {
        return match ($decision->reason) {
            CutReason::Silence => sprintf('Silence removed (%s s).', self::seconds($decision->endMs - $decision->startMs)),
            CutReason::Filler => 'Hesitation removed.',
            CutReason::FillerWord => 'Filler word removed.',
            CutReason::Stutter => 'False start removed.',
            CutReason::Repetition => 'Repeated words removed.',
            CutReason::VocalSound => 'Non-verbal sound removed.',
            CutReason::PauseMarker => 'Spoken pause marker removed.',
            CutReason::RoboticPause => sprintf(
                'Gap closed to %s s.',
                self::seconds((int) ($decision->evidence['target_gap_ms'] ?? 150)),
            ),
            CutReason::Manual => 'Your range.',
            default => 'Proposed automatically.',
        };
    }

    private static function seconds(int $milliseconds): string
    {
        return rtrim(rtrim(sprintf('%.2F', $milliseconds / 1_000), '0'), '.');
    }
}
