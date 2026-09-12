<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline\Producers;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;
use Modules\VideoEdits\Domain\Services\SpeechDisfluencyDetector;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\SpeechDetection;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;

/**
 * V2 — proposes removing spoken disfluencies (US-10).
 *
 * It joins the pipeline the way EX-2 promised: by being tagged as another
 * {@see CutDecisionProducer}. Validation, planning, rendering, persistence and
 * deletion are untouched — this class only produces decisions in the same
 * contract a V1 manual range uses (EX-1, EX-3).
 *
 * The work it does — extract audio, transcribe, detect — is expensive, so it
 * short-circuits hard: no speech cleanup requested, no audio track, or no
 * enabled categories all return before a single byte is uploaded.
 */
final readonly class SpeechDecisionProducer implements CutDecisionProducer
{
    public const string NAME = 'speech_detector';

    private const string AUDIO_FILE = 'speech-audio.mp3';

    public function __construct(
        private VideoEditorPort $editor,
        private TranscriptionPort $transcriber,
        private TranscriptStorePort $transcripts,
        private VideoEditWorkspacePort $workspace,
        private SpeechDisfluencyDetector $detector,
        private Config $config,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(DecisionContext $context): bool
    {
        return $context->mode === VideoEditMode::AutoEdit
            && ($context->parameters['speech_cleanup']['enabled'] ?? false) === true
            // Transcription of a silent track costs money and returns nothing.
            && $context->workingProbe->hasAudio;
    }

    /**
     * @return list<CutDecision>
     */
    public function produce(DecisionContext $context): array
    {
        $categories = $this->requestedCategories($context);

        if ($categories === []) {
            return [];
        }

        $transcript = $this->transcript($context);

        if ($transcript->isEmpty()) {
            return [];
        }

        $context->enterStage(ProcessingStage::SpeechDetection);
        $detections = $this->detector->detect($transcript, $categories);

        return array_map(
            fn (SpeechDetection $detection): CutDecision => new CutDecision(
                producer: self::NAME,
                reason: $detection->category->toCutReason(),
                origin: DecisionOrigin::Transcription,
                startMs: $detection->startMs,
                endMs: $detection->endMs,
                confidence: $detection->confidence,
                evidence: [
                    ...$detection->evidence,
                    'category' => $detection->category->value,
                ],
            ),
            $detections,
        );
    }

    /**
     * A stored transcript for the same sources in the same order is reused
     * verbatim (US-11) — re-editing a 20-minute recording should not be a
     * second transcription bill and a second wait.
     */
    private function transcript(DecisionContext $context): Transcript
    {
        $contentKey = $context->contentKey();
        $reusable = $context->ownerId === null || $context->sourceFingerprints === []
            ? null
            : $this->transcripts->findReusable($context->ownerId, $contentKey);

        if ($reusable !== null) {
            return $reusable;
        }

        // Inside the edit's own workspace, so FR-18 wipes the extracted speech
        // with everything else when processing ends.
        $audioPath = $this->workspace->path((string) $context->videoEditUuid, self::AUDIO_FILE);

        $transcript = $this->transcribeFresh($context, $audioPath);

        if ($context->videoEditId !== null && $context->sourceFingerprints !== []) {
            $this->transcripts->store(
                $context->videoEditId,
                $contentKey,
                $transcript,
                'openai',
                (string) $this->config->get('video-edit.speech.openai.model'),
            );
        }

        return $transcript;
    }

    private function transcribeFresh(DecisionContext $context, string $audioPath): Transcript
    {
        $context->enterStage(ProcessingStage::AudioExtraction);
        $this->editor->extractAudio(
            $context->workingPath,
            $audioPath,
            (int) $this->config->get('video-edit.speech.max_audio_bytes'),
        );

        $language = $context->parameters['speech_cleanup']['language']
            ?? $this->config->get('video-edit.speech.default_language');

        $context->enterStage(ProcessingStage::Transcription);

        return $this->transcriber->transcribe(
            $audioPath,
            is_string($language) && $language !== '' ? $language : null,
        );
    }

    /**
     * Absent categories mean "all of them" — that is what a user who switched
     * on automatic cleanup without opening the options asked for. Naming them
     * narrows the run, and the ones left out stay visible in the summary as not
     * applied (US-10).
     *
     * @return list<SpeechCategory>
     */
    private function requestedCategories(DecisionContext $context): array
    {
        $requested = $context->parameters['speech_cleanup']['categories'] ?? null;

        if (! is_array($requested) || $requested === []) {
            return SpeechCategory::all();
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): ?SpeechCategory => is_string($value) ? SpeechCategory::tryFrom($value) : null,
            $requested,
        )));
    }
}
