<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline\Producers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Exceptions\AiConsentRequiredException;
use Modules\VideoEdits\Domain\Exceptions\CutReviewRequiredException;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\Ports\AiEditAnalysisPort;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\Ports\ScriptProviderPort;
use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\Services\SpeechPaceAnalyzer;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * V3 — AI edit (US-12/13/14).
 *
 * Like every producer before it, this joins by being tagged (EX-2): validation,
 * planning, rendering, persistence and deletion were not touched to make V3
 * work.
 *
 * **The hybrid.** Whisper (V2) supplies the ruler — every word's exact
 * milliseconds. The AI supplies the judgement — which words are a spoken
 * "PAUSA", the failed take before it, or a word said wrong against the script.
 * The model answers in word indices and this class resolves them against the
 * transcript, so the cut is as frame-accurate as a V1 manual range even though
 * the model only reasons in `MM:SS`.
 *
 * **Consent gate (R9).** Nothing is sent anywhere until the user has explicitly
 * agreed for this edit. The check is first, before the transcript is even read.
 *
 * **Human review (OWASP LLM06).** The AI never cuts on its own. The first pass
 * analyses, stores every proposal as a reviewable cut and stops the pipeline
 * with {@see CutReviewRequiredException}. Once the owner has chosen, the next
 * pass turns exactly the approved cuts into decisions — without calling the
 * model again, so what renders is what was reviewed.
 *
 * **What it will not do (R6).** Editorial findings — "REDUCIR", pacing, broad
 * drift from the script — are stored as report recommendations and never
 * become cuts, approved or not. The speaking pace is measured here from the
 * word timings, not asked of the model, and joins them as a `pacing` one.
 */
final readonly class AiDecisionProducer implements CutDecisionProducer
{
    public const string NAME = 'ai_analyzer';

    public function __construct(
        private AiEditAnalysisPort $analyzer,
        private ScriptProviderPort $scripts,
        private TranscriptStorePort $transcripts,
        private AiReportStorePort $reports,
        private AiCutReviewStorePort $reviews,
        private SpeechPaceAnalyzer $pace,
        private Config $config,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(DecisionContext $context): bool
    {
        return $context->mode === VideoEditMode::AiEdit
            && ($context->parameters['ai_edit']['enabled'] ?? false) === true;
    }

    /**
     * @return list<CutDecision>
     *
     * @throws AiConsentRequiredException
     * @throws CutReviewRequiredException when proposals are waiting for the owner
     */
    public function produce(DecisionContext $context): array
    {
        // Before anything is read, let alone sent (R9).
        if (($context->parameters['ai_edit']['consented'] ?? false) !== true) {
            throw new AiConsentRequiredException;
        }

        // A proposal nobody can review must never be cut, so without an edit to
        // hang the review on, the model is not even asked.
        if ($context->videoEditId === null) {
            return [];
        }

        $review = $this->reviews->forEdit($context->videoEditId);

        if ($review?->isResolved() === true) {
            return self::approvedDecisions($review);
        }

        if ($review !== null) {
            // A duplicate or stale job must not render around an open review.
            throw new CutReviewRequiredException(count($review->cuts));
        }

        $transcript = $this->transcript($context);

        if ($transcript === null || $transcript->isEmpty()) {
            // AI edit runs on speech. Without a transcript there is nothing to
            // reason about, and proposing cuts blind is worse than none.
            return [];
        }

        $script = $this->script($context);

        $context->enterStage(ProcessingStage::AiAnalysis);
        $analysis = $this->analyzer->analyze(
            $transcript,
            $script,
            self::stringParameter($context, 'instructions'),
            self::intParameter($context, 'target_duration_minutes'),
        );

        $pace = $this->pace->recommendation($transcript, $this->silenceThresholdMs($context));

        if ($pace !== null) {
            $analysis = $analysis->withRecommendation($pace);
        }

        $this->reports->store($context->videoEditId, $analysis);

        $cuts = $this->reviewableCuts($analysis, $transcript);

        if ($cuts === []) {
            $this->reviews->store($context->videoEditId, AiCutReview::nothingProposed(CarbonImmutable::now()));

            return [];
        }

        $this->reviews->store($context->videoEditId, AiCutReview::pending($cuts));

        throw new CutReviewRequiredException(count($cuts));
    }

    /**
     * Word indices become exact milliseconds here — the whole point of the
     * hybrid. Every proposal is kept, low confidence included: the owner sees
     * it unticked instead of it being thrown away unseen.
     *
     * @return list<AiReviewableCut>
     */
    private function reviewableCuts(AiAnalysis $analysis, Transcript $transcript): array
    {
        $threshold = (float) $this->config->get('video-edit.ai.preselect_above_confidence');
        $contextWords = (int) $this->config->get('video-edit.ai.review_context_words', 8);

        return array_values(array_map(
            static fn (AiCutProposal $proposal): AiReviewableCut => new AiReviewableCut(
                id: (string) Str::uuid7(),
                reason: $proposal->reason,
                startMs: $transcript->words[$proposal->startWordIndex]->startMs,
                endMs: $transcript->words[$proposal->endWordIndex]->endMs,
                confidence: $proposal->confidence,
                text: self::words($transcript, $proposal->startWordIndex, $proposal->endWordIndex - $proposal->startWordIndex + 1),
                contextBefore: self::words($transcript, max(0, $proposal->startWordIndex - $contextWords), min($contextWords, $proposal->startWordIndex)),
                contextAfter: self::words($transcript, $proposal->endWordIndex + 1, $contextWords),
                explanation: $proposal->evidence,
                preselected: $proposal->confidence >= $threshold,
            ),
            // A zero-length word would make an empty range; the validator would
            // reject it later anyway, so it is not worth a review row.
            array_filter(
                $analysis->cutProposals,
                static fn (AiCutProposal $proposal): bool => $transcript->words[$proposal->endWordIndex]->endMs
                    > $transcript->words[$proposal->startWordIndex]->startMs,
            ),
        ));
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
                origin: DecisionOrigin::Ai,
                startMs: $cut->startMs,
                endMs: $cut->endMs,
                confidence: $cut->confidence,
                evidence: [
                    'text' => mb_substr($cut->text, 0, 300),
                    'review_cut_id' => $cut->id,
                ],
            ),
            $review->approvedCuts(),
        );
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

    /**
     * V3 reuses the V2 transcript rather than transcribing again: an AI edit
     * always runs speech cleanup first, so the transcript is already stored
     * against this edit (US-11).
     */
    private function transcript(DecisionContext $context): ?Transcript
    {
        if ($context->ownerId === null || $context->sourceFingerprints === []) {
            return null;
        }

        return $this->transcripts->findReusable($context->ownerId, $context->contentKey());
    }

    private function script(DecisionContext $context): ?ScriptDocument
    {
        if ($context->videoEditId === null) {
            return null;
        }

        $script = $this->scripts->forEdit($context->videoEditId);

        return $script?->truncated((int) $this->config->get('video-edit.ai.max_script_characters'));
    }

    /**
     * The gap silence removal will cut in this edit, or null when it is off and
     * every pause reaches the final video.
     */
    private function silenceThresholdMs(DecisionContext $context): ?int
    {
        $silence = (array) ($context->parameters['silence_removal'] ?? []);

        if (($silence['enabled'] ?? false) !== true) {
            return null;
        }

        $seconds = $silence['threshold_seconds'] ?? $this->config->get('video-edit.silence.default_threshold_seconds');

        return (int) round((float) $seconds * 1000);
    }

    private static function stringParameter(DecisionContext $context, string $key): ?string
    {
        $value = $context->parameters['ai_edit'][$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function intParameter(DecisionContext $context, string $key): ?int
    {
        $value = $context->parameters['ai_edit'][$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
