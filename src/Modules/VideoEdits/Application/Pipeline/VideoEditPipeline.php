<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Exceptions\InvalidMediaException;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\Ports\VideoEditWorkspacePort;
use Modules\VideoEdits\Domain\Services\CutDecisionValidator;
use Modules\VideoEdits\Domain\Services\CutPlanner;
use Modules\VideoEdits\Domain\ValueObjects\ContentFingerprint;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\ValidatedDecision;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Shared\Domain\Ports\StoragePort;
use Throwable;

/**
 * Runs one edit through the ordered stages (plan §3.1, EX-7):
 * Download → Merge → Analysis → Plan cuts → Render → Publish.
 *
 * Decision producers are the only variable part (EX-2); validation, planning,
 * rendering and persistence consume the single CutDecision contract (EX-1/EX-3).
 */
final readonly class VideoEditPipeline
{
    private const string MERGED_FILE = 'merged.mp4';

    private const string RESULT_FILE = 'result.mp4';

    public function __construct(
        private VideoEditRepositoryPort $edits,
        private StoragePort $storage,
        private VideoEditorPort $editor,
        private VideoEditWorkspacePort $workspace,
        private DecisionProducerRegistry $producers,
        private CutDecisionValidator $validator,
        private CutPlanner $planner,
        private Config $config,
    ) {}

    /**
     * Expects the edit in `processing`, loaded with its sources and owner.
     *
     * @throws InvalidMediaException
     * @throws InvalidCutRangesException
     */
    public function run(VideoEditEloquentModel $edit): void
    {
        $progress = new ProgressReporter(
            $this->edits,
            $edit->uuid,
            (int) $this->config->get('video-edit.progress.min_percent_step'),
            (float) $this->config->get('video-edit.progress.min_interval_seconds'),
            static fn (): float => microtime(true),
        );

        /** @var list<VideoEditSourceEloquentModel> $sources */
        $sources = $edit->sources->sortBy('position')->values()->all();

        // ── Download ────────────────────────────────────────────────────────
        $progress->startStage(ProcessingStage::Download);
        [$inputPaths, $inputProbes] = $this->download($edit, $sources, $progress);
        $sourcesDurationMs = array_sum(array_map(static fn (MediaProbe $probe): int => $probe->durationMs, $inputProbes));
        $this->assertWithinDurationLimit($sourcesDurationMs);
        $this->assertManualRangesFit($edit->parameters, $sourcesDurationMs);

        $profile = OutputProfile::fromFirstSource(
            $inputProbes[0],
            (int) $this->config->get('video-edit.output.max_width'),
            (int) $this->config->get('video-edit.output.max_height'),
            (int) $this->config->get('video-edit.output.max_frame_rate'),
            (int) $this->config->get('video-edit.output.audio_sample_rate'),
            (int) $this->config->get('video-edit.output.audio_channels'),
        );

        // ── Merge ───────────────────────────────────────────────────────────
        $progress->startStage(ProcessingStage::Merge);
        [$workingPath, $workingProbe] = $this->merge($edit, $inputPaths, $inputProbes, $profile, $progress);

        // ── Analysis ────────────────────────────────────────────────────────
        $progress->startStage(ProcessingStage::Analysis);
        $decisions = $this->produceDecisions(
            new DecisionContext($edit->mode, $edit->parameters, $workingPath, $workingProbe),
            $sourcesDurationMs,
        );

        // ── Plan cuts ───────────────────────────────────────────────────────
        $progress->startStage(ProcessingStage::PlanCuts);
        $validated = $this->validator->validate($decisions, $workingProbe->durationMs);
        $this->assertUserDecisionsAccepted($validated);
        $plan = $this->planner->plan($validated, $workingProbe->durationMs);

        // ── Render ──────────────────────────────────────────────────────────
        $progress->startStage(ProcessingStage::Render);
        $resultLocalPath = $workingPath;

        if ($edit->mode !== VideoEditMode::Merge) {
            $resultLocalPath = $this->workspace->path($edit->uuid, self::RESULT_FILE);
            $this->editor->render(
                $workingPath,
                $workingProbe,
                $plan->keepRanges,
                $resultLocalPath,
                $profile,
                static fn (int $percent) => $progress->advanceStage(ProcessingStage::Render, $percent),
            );
        }

        // ── Publish ─────────────────────────────────────────────────────────
        $progress->startStage(ProcessingStage::Publish);
        $resultPath = $this->resultPath($edit);
        $this->storage->putFromPath($resultPath, $resultLocalPath);
        $progress->finish();

        $completed = $this->edits->completeWithPlan(
            $edit->uuid,
            $plan,
            $resultPath,
            (int) filesize($resultLocalPath),
            $this->effectiveSettings($profile),
            $this->warnings($inputProbes, $plan->rejectedDecisionCount()),
        );

        if (! $completed) {
            $this->storage->delete($resultPath);

            return;
        }

        $this->deleteSources($edit->uuid, $sources);
    }

    /**
     * @param  list<VideoEditSourceEloquentModel>  $sources
     * @return array{0: list<string>, 1: list<MediaProbe>}
     */
    private function download(VideoEditEloquentModel $edit, array $sources, ProgressReporter $progress): array
    {
        $multiplier = (int) $this->config->get('video-edit.workspace.free_space_multiplier');
        $requiredBytes = array_sum(array_map(
            static fn (VideoEditSourceEloquentModel $source): int => $source->size_bytes ?? $source->declared_size_bytes,
            $sources,
        )) * $multiplier;

        $this->workspace->prepare($edit->uuid, $requiredBytes);
        $allowedContainers = (array) $this->config->get('video-edit.limits.allowed_containers');
        $paths = [];
        $probes = [];

        foreach ($sources as $index => $source) {
            $localPath = $this->workspace->path($edit->uuid, "source-{$source->position}.{$source->extension}");
            $this->storage->copyToLocal((string) $source->storage_path, $localPath);

            $probe = $this->editor->probe($localPath);

            if (! $probe->hasVideo || ! $probe->isAllowedContainer($allowedContainers)) {
                throw InvalidMediaException::unsupportedSource($source->position);
            }

            $this->edits->recordSourceProbe($source->uuid, $probe, new ContentFingerprint((string) hash_file('sha256', $localPath)));

            $paths[] = $localPath;
            $probes[] = $probe;
            $progress->advanceStage(ProcessingStage::Download, intdiv(($index + 1) * 100, count($sources)));
        }

        return [$paths, $probes];
    }

    /**
     * @param  list<string>  $inputPaths
     * @param  list<MediaProbe>  $inputProbes
     * @return array{0: string, 1: MediaProbe}
     */
    private function merge(
        VideoEditEloquentModel $edit,
        array $inputPaths,
        array $inputProbes,
        OutputProfile $profile,
        ProgressReporter $progress,
    ): array {
        if (count($inputPaths) === 1) {
            return [$inputPaths[0], $inputProbes[0]];
        }

        $mergedPath = $this->workspace->path($edit->uuid, self::MERGED_FILE);

        $this->editor->merge(
            $inputPaths,
            $inputProbes,
            $mergedPath,
            $profile,
            intermediate: $edit->mode !== VideoEditMode::Merge,
            onProgress: static fn (int $percent) => $progress->advanceStage(ProcessingStage::Merge, $percent),
        );

        return [$mergedPath, $this->editor->probe($mergedPath)];
    }

    /**
     * @return list<CutDecision>
     */
    private function produceDecisions(DecisionContext $context, int $sourcesDurationMs): array
    {
        $decisions = [];

        foreach ($this->producers->forContext($context) as $producer) {
            array_push($decisions, ...$producer->produce($context));
        }

        // User ranges were checked against the summed clip durations before the
        // merge; a merged file can come out a few milliseconds shorter, so ranges
        // ending in that gap are clamped instead of failing (P1).
        return array_map(
            static fn (CutDecision $decision): CutDecision => $decision->origin === DecisionOrigin::User
                && $decision->endMs > $context->workingProbe->durationMs
                && $decision->endMs <= $sourcesDurationMs
                    ? new CutDecision(
                        $decision->producer,
                        $decision->reason,
                        $decision->origin,
                        $decision->startMs,
                        $context->workingProbe->durationMs,
                        $decision->confidence,
                        $decision->evidence,
                    )
                    : $decision,
            $decisions,
        );
    }

    private function assertWithinDurationLimit(int $sourcesDurationMs): void
    {
        $maximumMs = (int) $this->config->get('video-edit.limits.max_total_duration_seconds') * 1000;

        if ($sourcesDurationMs > $maximumMs) {
            throw InvalidMediaException::tooLong($sourcesDurationMs, $maximumMs);
        }
    }

    /**
     * Duration-bound check of the user's ranges before any merge, analysis or render (P1 · AD-16).
     *
     * @param  array<string, mixed>  $parameters
     */
    private function assertManualRangesFit(array $parameters, int $sourcesDurationMs): void
    {
        $errors = [];

        foreach (array_values((array) ($parameters['manual_ranges'] ?? [])) as $index => $range) {
            if ((int) $range['end_ms'] > $sourcesDurationMs) {
                $errors[] = ['index' => $index, 'error' => 'end_beyond_duration'];
            }
        }

        if ($errors !== []) {
            throw InvalidCutRangesException::forManualRanges($errors);
        }
    }

    /**
     * A user range the validator rejects fails the whole edit (P1); rejected
     * system or AI decisions are only reported (EX-3).
     *
     * @param  list<ValidatedDecision>  $validated
     */
    private function assertUserDecisionsAccepted(array $validated): void
    {
        $errors = [];

        foreach ($validated as $decision) {
            if (! $decision->isApplied() && $decision->decision->origin === DecisionOrigin::User) {
                $errors[] = [
                    'index' => (int) ($decision->decision->evidence['range_index'] ?? 0),
                    'error' => (string) $decision->rejectionReason?->value,
                ];
            }
        }

        if ($errors !== []) {
            throw InvalidCutRangesException::forManualRanges($errors);
        }
    }

    private function resultPath(VideoEditEloquentModel $edit): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            $this->config->get('video-edit.storage.path_prefix'),
            $edit->user->uuid,
            $edit->uuid,
            self::RESULT_FILE,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function effectiveSettings(OutputProfile $profile): array
    {
        return [
            'noise_floor_db' => (int) $this->config->get('video-edit.silence.noise_floor_db'),
            'padding_ms' => (int) $this->config->get('video-edit.silence.padding_ms'),
            'min_kept_fragment_ms' => (int) $this->config->get('video-edit.cuts.min_kept_fragment_ms'),
            'output_profile' => [
                'width' => $profile->width,
                'height' => $profile->height,
                'frame_rate' => $profile->frameRate,
                'audio_sample_rate' => $profile->audioSampleRate,
                'audio_channels' => $profile->audioChannels,
            ],
        ];
    }

    /**
     * @param  list<MediaProbe>  $probes
     * @return list<string>
     */
    private function warnings(array $probes, int $rejectedDecisionCount): array
    {
        $warnings = [];

        foreach ($probes as $index => $probe) {
            if (! $probe->hasAudio) {
                $warnings[] = sprintf('Clip %d has no audio; silence was added for its duration.', $index + 1);
            }
        }

        if ($rejectedDecisionCount > 0) {
            $warnings[] = sprintf('%d detected cut(s) were not applied; see the decision list for the reasons.', $rejectedDecisionCount);
        }

        return $warnings;
    }

    /**
     * Sources are deleted as soon as the result is safe (FR-9). A file that
     * cannot be deleted now keeps its path so the scheduled cleanup retries it.
     *
     * @param  list<VideoEditSourceEloquentModel>  $sources
     */
    private function deleteSources(string $videoEditUuid, array $sources): void
    {
        $deleted = [];

        foreach ($sources as $source) {
            try {
                $this->storage->delete((string) $source->storage_path);
                $deleted[] = $source->uuid;
            } catch (Throwable) {
                continue;
            }
        }

        $this->edits->markSourcesPurged($videoEditUuid, $deleted);
    }
}
