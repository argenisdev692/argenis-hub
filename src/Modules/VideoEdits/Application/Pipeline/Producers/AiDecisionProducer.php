<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline\Producers;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Exceptions\AiConsentRequiredException;
use Modules\VideoEdits\Domain\Ports\AiEditAnalysisPort;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\Ports\ScriptProviderPort;
use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;

/**
 * V3 — AI edit (US-12/13/14).
 *
 * Like every producer before it, this joins by being tagged (EX-2): validation,
 * planning, rendering, persistence and deletion were not touched to make V3
 * work.
 *
 * **The hybrid.** Whisper (V2) supplies the ruler — every word's exact
 * milliseconds. The AI supplies the judgement — which words are a spoken
 * "PAUSA" and which are the failed take before it. The model answers in word
 * indices and this class resolves them against the transcript, so the cut is as
 * frame-accurate as a V1 manual range even though the model only reasons in
 * `MM:SS`.
 *
 * **Consent gate (R9).** Nothing is sent anywhere until the user has explicitly
 * agreed for this edit. The check is first, before the transcript is even read.
 *
 * **What it will not do (R6).** Only `pause_marker` and `retake` become cuts,
 * and only above the configured confidence. Editorial findings — off-script,
 * "REDUCIR", pacing — are stored as report recommendations and never applied.
 */
final readonly class AiDecisionProducer implements CutDecisionProducer
{
    public const string NAME = 'ai_analyzer';

    public function __construct(
        private AiEditAnalysisPort $analyzer,
        private ScriptProviderPort $scripts,
        private TranscriptStorePort $transcripts,
        private AiReportStorePort $reports,
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
     */
    public function produce(DecisionContext $context): array
    {
        // Before anything is read, let alone sent (R9).
        if (($context->parameters['ai_edit']['consented'] ?? false) !== true) {
            throw new AiConsentRequiredException;
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

        $this->storeReport($context, $analysis);

        return $this->toDecisions($analysis, $transcript);
    }

    /**
     * Word indices become exact milliseconds here — the whole point of the
     * hybrid. Below-threshold proposals are dropped rather than emitted:
     * the report already records that the model suggested them, and letting
     * them reach the planner would apply them.
     *
     * @return list<CutDecision>
     */
    private function toDecisions(AiAnalysis $analysis, Transcript $transcript): array
    {
        $threshold = (float) $this->config->get('video-edit.ai.auto_apply_above_confidence');
        $decisions = [];

        foreach ($analysis->cutProposals as $proposal) {
            if ($proposal->confidence < $threshold) {
                continue;
            }

            $decisions[] = new CutDecision(
                producer: self::NAME,
                reason: $proposal->reason,
                origin: DecisionOrigin::Ai,
                startMs: $transcript->words[$proposal->startWordIndex]->startMs,
                endMs: $transcript->words[$proposal->endWordIndex]->endMs,
                confidence: $proposal->confidence,
                evidence: self::evidence($proposal),
            );
        }

        return $decisions;
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function evidence(AiCutProposal $proposal): array
    {
        return [
            'text' => $proposal->evidence,
            'start_word_index' => $proposal->startWordIndex,
            'end_word_index' => $proposal->endWordIndex,
        ];
    }

    /**
     * Only the validated recommendations and the conclusion are kept — never
     * the raw provider response (decision R10), so a hard delete leaves nothing
     * of the user's content behind.
     */
    private function storeReport(DecisionContext $context, AiAnalysis $analysis): void
    {
        if ($context->videoEditId === null) {
            return;
        }

        $this->reports->store($context->videoEditId, $analysis);
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
