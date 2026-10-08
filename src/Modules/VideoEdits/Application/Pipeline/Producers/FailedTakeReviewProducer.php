<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline\Producers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Exceptions\CutReviewRequiredException;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\Services\FailedTakeDetector;
use Modules\VideoEdits\Domain\Services\RestartDetector;
use Modules\VideoEdits\Domain\Services\SpeechDisfluencyDetector;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\FailedTake;
use Modules\VideoEdits\Domain\ValueObjects\FailedTakeAnchor;
use Modules\VideoEdits\Domain\ValueObjects\FailedTakeAnchorKind;
use Modules\VideoEdits\Domain\ValueObjects\Restart;
use Modules\VideoEdits\Domain\ValueObjects\SpeechDetection;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Turns every spoken pause marker into a REVIEWABLE proposal for the failed take
 * before it, in every mode that runs speech cleanup (OBSERVACIONES round 2,
 * item 2).
 *
 * **Why this is separate from the marker cut.** {@see SpeechDecisionProducer}
 * removes "pausa acá" and nothing else, and that is correct: the marker is a
 * literal token match that cannot be wrong. But the words the speaker was
 * correcting stay, so the delivered audio reads [wrong phrase] → [jump] →
 * [right phrase] — the incoherent splice the supervisor kept reporting, in a mode
 * that never had a way to fix it. An auto edit had no `retake` at all; only an
 * AI edit could propose one, and only if Gemini noticed.
 *
 * **Why it is still a proposal and not a cut.** Removing the marker is a fact
 * about the audio; removing the take is a judgement about intent, and the module's
 * stated preference is that a wrongly removed real word costs the owner a
 * re-record while a missed take costs one unticked box. So the marker keeps being
 * cut deterministically, the take is stored on the existing cut review, and the
 * edit parks in `awaiting_review` — the same human-in-the-loop the AI cuts have
 * always had (OWASP LLM06), reached here without a model and without consent.
 *
 * **Why AI edit is excluded.** It asks the model for `retake` cuts itself, behind
 * its own consent gate, and reviews them on the same record. Running both would
 * put two producers in a race for one review, and the deterministic take would
 * pre-empt a judgement the model is better placed to make from the script.
 *
 * **No second transcription.** The transcript was stored by
 * {@see SpeechDecisionProducer} earlier in the same pass — which is why this
 * producer is tagged after it — so the marker re-detection here is a pure pass
 * over already-measured word timings. Reordering the two tags silently disables
 * the feature rather than breaking it, which is the failure mode worth naming.
 *
 * Nothing proposed stores no review record at all: unlike the AI path there is
 * no paid call to avoid repeating on a retry, and an empty record would only
 * show up as a review the owner can never open.
 */
final readonly class FailedTakeReviewProducer implements CutDecisionProducer
{
    public const string NAME = 'failed_take_reviewer';

    /**
     * Above every {@see FailedTakeBoundary} confidence, because it is earned by two
     * signals agreeing rather than by one signal being confident.
     */
    private const float CORROBORATED_CONFIDENCE = 0.99;

    public function __construct(
        private TranscriptStorePort $transcripts,
        private SpeechDisfluencyDetector $disfluencies,
        private FailedTakeDetector $failedTakes,
        private RestartDetector $restarts,
        private AiCutReviewStorePort $reviews,
        private Config $config,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(DecisionContext $context): bool
    {
        // An edit with nothing to hang a review on can never be resolved, so
        // the model is not asked either — here the proposal is simply dropped.
        if ($context->videoEditId === null || $context->ownerId === null || $context->sourceFingerprints === []) {
            return false;
        }

        $markerWanted = $this->markerWanted($context);
        $restartWanted = $this->restartWanted();

        // A restart is found from repetition alone, so it must not be gated on the
        // marker category: the recordings that need it most are the ones where the
        // marker was never transcribed.
        if (! $markerWanted && ! $restartWanted) {
            return false;
        }

        return (bool) $this->config->get('video-edit.failed_take.enabled')
            && $context->mode->acceptsCutDecisions()
            && $context->mode !== VideoEditMode::AiEdit
            // Mirrors SpeechDecisionProducer: no transcript is produced without
            // these, so a proposal would have nothing to be measured against.
            && ($context->parameters['speech_cleanup']['enabled'] ?? false) === true
            && $context->workingProbe->hasAudio;
    }

    /**
     * @return list<CutDecision>
     *
     * @throws CutReviewRequiredException when proposals are waiting for the owner
     */
    public function produce(DecisionContext $context): array
    {
        $videoEditId = $context->videoEditId;

        if ($videoEditId === null) {
            return [];
        }

        $review = $this->reviews->forEdit($videoEditId);

        if ($review?->isResolved() === true) {
            return self::approvedDecisions($review);
        }

        if ($review !== null) {
            // A duplicate or stale job must not render around an open review.
            throw new CutReviewRequiredException(count($review->cuts));
        }

        $transcript = $this->transcripts->findReusable($context->ownerId, $context->contentKey());

        if ($transcript === null || $transcript->isEmpty()) {
            return [];
        }

        // Two independent signals, one shape. The marker is found again here
        // rather than handed over: the detection is a pure pass over word timings
        // the transcript already carries, and reading it from the store keeps the
        // two producers from having to agree on an intermediate value.
        $markers = $this->markerWanted($context)
            ? $this->disfluencies->detect($transcript, [SpeechCategory::PauseMarker])
            : [];
        $restarts = $this->restartWanted() ? $this->restarts->detect($transcript->words) : [];

        $anchors = $this->anchors($markers, $restarts);

        if ($anchors === []) {
            return [];
        }

        $takes = $this->failedTakes->detect($transcript->words, $anchors);

        if ($takes === []) {
            return [];
        }

        $this->reviews->store(
            $videoEditId,
            AiCutReview::pending(array_map(
                fn (FailedTake $take): AiReviewableCut => $this->reviewableCut($take, $transcript),
                $takes,
            )),
        );

        throw new CutReviewRequiredException(count($takes));
    }

    /**
     * Both signals, merged into one list of positions to measure back from.
     *
     * A marker and a restart found for the same abandoned attempt are ONE anchor
     * marked corroborated, not two anchors a few hundred milliseconds apart: the
     * walk-back is bounded by the previous anchor, so a duplicate would produce a
     * second, truncated proposal for words the first one already covers. This is
     * also where the strongest evidence in the module comes from — the two signals
     * fail independently (the transcriber drops markers; repetition does not
     * depend on the marker being transcribed), so agreeing on a span is real
     * corroboration rather than the same fact counted twice.
     *
     * @param  list<SpeechDetection>  $markers
     * @param  list<Restart>  $restarts
     * @return list<FailedTakeAnchor>
     */
    private function anchors(array $markers, array $restarts): array
    {
        $anchors = array_map(
            static fn (SpeechDetection $marker): FailedTakeAnchor => FailedTakeAnchor::pauseMarker($marker->startMs),
            $markers,
        );

        foreach ($restarts as $restart) {
            $anchors[] = FailedTakeAnchor::restart(
                $restart,
                // A marker sits where the speaker flagged the mistake, which is at
                // or just before the restart. Either way it is inside the same
                // abandoned attempt.
                corroborated: $this->markerNear($anchors, $restart),
            );
        }

        usort(
            $anchors,
            static fn (FailedTakeAnchor $a, FailedTakeAnchor $b): int => $a->takeEndsBeforeMs <=> $b->takeEndsBeforeMs,
        );

        return $this->merge($anchors);
    }

    /**
     * @param  list<FailedTakeAnchor>  $anchors
     */
    private function markerNear(array $anchors, Restart $restart): bool
    {
        foreach ($anchors as $anchor) {
            if ($anchor->kind !== FailedTakeAnchorKind::PauseMarker) {
                continue;
            }

            $distanceMs = abs($anchor->takeEndsBeforeMs - $restart->restartStartMs);

            if ($distanceMs <= $this->config->get('video-edit.restart.marker_proximity_ms', 2_000)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One abandoned attempt, one anchor.
     *
     * @param  list<FailedTakeAnchor>  $anchors  sorted by start time
     * @return list<FailedTakeAnchor>
     */
    private function merge(array $anchors): array
    {
        $merged = [];
        $proximityMs = (int) $this->config->get('video-edit.restart.marker_proximity_ms', 2_000);

        foreach ($anchors as $anchor) {
            $previous = $merged[array_key_last($merged)] ?? null;

            if ($previous === null
                || $anchor->takeEndsBeforeMs - $previous->takeEndsBeforeMs > $proximityMs
                || $previous->kind === $anchor->kind) {
                $merged[] = $anchor;

                continue;
            }

            // A marker and a restart this close are the same event; keep the
            // earlier position and remember that both signals found it.
            if ($anchor->takeEndsBeforeMs < $previous->takeEndsBeforeMs) {
                $merged[array_key_last($merged)] = new FailedTakeAnchor(
                    takeEndsBeforeMs: $anchor->takeEndsBeforeMs,
                    seekFromMs: $anchor->seekFromMs,
                    corroborated: true,
                    kind: $anchor->kind,
                );

                continue;
            }

            $merged[array_key_last($merged)] = new FailedTakeAnchor(
                takeEndsBeforeMs: $previous->takeEndsBeforeMs,
                seekFromMs: $previous->seekFromMs,
                corroborated: true,
                kind: $previous->kind,
            );
        }

        return $merged;
    }

    /**
     * Words and context come from the transcript, not from a detector's guess,
     * so what the owner approves is exactly what was heard in that span.
     */
    private function reviewableCut(FailedTake $take, Transcript $transcript): AiReviewableCut
    {
        $contextWords = (int) $this->config->get('video-edit.failed_take.context_words', 8);
        $confidence = self::confidenceFor($take);

        return new AiReviewableCut(
            id: (string) Str::uuid7(),
            reason: CutReason::Retake,
            startMs: $take->startMs,
            endMs: $take->endMs,
            confidence: $confidence,
            text: self::words($transcript, $take->startWordIndex, $take->wordCount()),
            contextBefore: self::words($transcript, max(0, $take->startWordIndex - $contextWords), min($contextWords, $take->startWordIndex)),
            // What follows is where the speaker went instead — the restart, or the
            // marker that flagged it. Either way it is why this span was proposed.
            contextAfter: self::words($transcript, $take->endWordIndex + 1, $contextWords),
            explanation: self::explanationFor($take),
            preselected: $confidence >= (float) $this->config->get('video-edit.failed_take.preselect_above_confidence'),
        );
    }

    /**
     * Corroboration outranks every boundary, including a clean sentence end.
     *
     * A sentence end says the take does not reach back past that word; it says
     * nothing about whether the words in between were the ones the speaker meant to
     * keep. Two independent signals agreeing on the same span does, which is why
     * it is the only way a proposal found from a bare repeated clause — which
     * otherwise sits below the preselect threshold — can start ticked.
     */
    private static function confidenceFor(FailedTake $take): float
    {
        return $take->corroborated ? self::CORROBORATED_CONFIDENCE : $take->boundary->confidence();
    }

    private static function explanationFor(FailedTake $take): string
    {
        if (! $take->corroborated) {
            return $take->boundary->explanation();
        }

        return 'A spoken "PAUSA" and the same words repeated after a pause both point here. '.$take->boundary->explanation();
    }

    /**
     * @return list<CutDecision>
     */
    private static function approvedDecisions(AiCutReview $review): array
    {
        return array_map(
            static fn (AiReviewableCut $cut): CutDecision => new CutDecision(
                producer: self::NAME,
                reason: $cut->reason,
                // Transcription, not AI: nothing here was ever sent to a
                // provider, and the span came from Whisper's own timings.
                origin: DecisionOrigin::Transcription,
                startMs: $cut->startMs,
                endMs: $cut->endMs,
                confidence: $cut->confidence,
                evidence: [
                    'text' => mb_substr($cut->text, 0, 300),
                    'review_cut_id' => $cut->id,
                    'detector' => 'failed_take',
                ],
            ),
            $review->approvedCuts(),
        );
    }

    private function restartWanted(): bool
    {
        return (bool) $this->config->get('video-edit.restart.enabled');
    }

    /**
     * Absent categories mean "all of them" — the same rule
     * {@see SpeechDecisionProducer} applies, so an owner who never opened the
     * options still gets the markers this producer depends on.
     */
    private function markerWanted(DecisionContext $context): bool
    {
        $requested = $context->parameters['speech_cleanup']['categories'] ?? null;

        if (! is_array($requested) || $requested === []) {
            return true;
        }

        return in_array(SpeechCategory::PauseMarker->value, $requested, true);
    }

    private static function words(Transcript $transcript, int $offset, int $length): string
    {
        return $length <= 0
            ? ''
            : implode(' ', array_map(
                static fn (TranscriptWord $word): string => $word->text,
                array_slice($transcript->words, $offset, $length),
            ));
    }
}
