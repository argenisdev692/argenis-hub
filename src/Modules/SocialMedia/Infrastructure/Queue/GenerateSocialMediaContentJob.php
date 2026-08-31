<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Queue;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\SocialMedia\Application\DTOs\ContentEvaluationData;
use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\PlatformContentData;
use Modules\SocialMedia\Domain\Enums\SocialMediaContentStatus;
use Modules\SocialMedia\Domain\Ports\SocialMediaAssetRendererPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentEvaluatorPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentGeneratorPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;
use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;
use Modules\SocialMedia\Infrastructure\Broadcasting\SocialMediaProgressNotifier;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * The Step 2 quality loop, in three separately-priced phases:
 *
 *   1. WRITE + JUDGE, up to {@see ContentQualityEvaluator::MAX_ITERATIONS}
 *      times — one text completion by the caller's provider, then one scoring
 *      pass by an independent model. Both are cheap, so a rejected attempt is
 *      cheap. Fresh Tavily research every iteration; the judge's explanations
 *      are fed back so the next attempt targets the scores that actually
 *      failed.
 *   2. PICK the best attempt (highest overall average, or the first that
 *      clears every threshold — the loop stops there).
 *   3. RENDER the artwork and voiceover, ONCE, for that winner only.
 *
 * Phase 3 used to sit inside phase 1: every iteration rendered 6 images and a
 * voiceover, so a run that needed all 5 attempts billed up to 30 images and 5
 * voiceovers and threw 24 of those images away. Moving it out is the single
 * largest cost reduction available in this pipeline, and it also shortens the
 * worst case enough that the loop stops flirting with the worker timeout.
 *
 * Dependencies are method-injected on {@see self::handle()} (not constructor
 * promotion) — the job is serialized onto the Redis queue, so only plain
 * scalars/DTOs belong on `$this`.
 */
/*
 * Timeout budget, worst case, `image_mode: full`:
 *   5 x (4 Tavily searches @ <=15s + 1 text generation + 1 scoring pass)
 *   + ONE render pass (6 images + 1 voiceover).
 * That is roughly a third of the old worst case, which multiplied the render
 * pass by 5 and could not fit two full iterations inside the previous 300s
 * ceiling. 900s is kept as headroom, not as an expectation.
 */
#[Queue('default')]
#[Tries(1)]
#[Timeout(900)]
final class GenerateSocialMediaContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $contentUuid,
        private readonly GenerateSocialMediaContentData $data,
        private readonly ?int $causerId = null,
    ) {}

    public function handle(
        SocialMediaContentGeneratorPort $generator,
        SocialMediaContentEvaluatorPort $judge,
        SocialMediaAssetRendererPort $renderer,
        SocialMediaContentRepositoryPort $repository,
        ContentQualityEvaluator $evaluator,
        SocialMediaProgressNotifier $progress,
        AuditPort $audit,
    ): void {
        $content = $repository->findByUuid($this->contentUuid);

        if ($content === null) {
            Log::warning('social_media.generation.content_missing', ['uuid' => $this->contentUuid]);

            return;
        }

        $causer = $this->causerId !== null ? User::find($this->causerId) : null;

        $best = null;
        $bestEvaluation = null;
        $previousWeaknesses = [];
        $iterationsRan = 0;

        for ($iteration = 1; $iteration <= ContentQualityEvaluator::MAX_ITERATIONS; $iteration++) {
            $iterationsRan = $iteration;

            $progress->notify($causer, $this->contentUuid, 'iteration_start', "Starting iteration {$iteration}…", $this->loopProgress($iteration), $iteration);

            try {
                $draft = $generator->generate($this->contentUuid, $this->data, $iteration, $previousWeaknesses, $causer);
                $evaluation = $judge->evaluate($this->contentUuid, $draft, $this->data, $iteration, $causer);
            } catch (Throwable $e) {
                Log::warning('social_media.generation.iteration_failed', [
                    'uuid' => $this->contentUuid,
                    'iteration' => $iteration,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($bestEvaluation === null || $evaluation->scores->overallAverage > $bestEvaluation->scores->overallAverage) {
                $best = $draft;
                $bestEvaluation = $evaluation;
            }

            if ($evaluation->scores->allScoresPass) {
                break;
            }

            $previousWeaknesses = $evaluator->identifyWeaknesses(
                $evaluation->scores->toThresholdMap(),
                $evaluation->scores->toExplanationMap(),
            );
        }

        if ($best === null || $bestEvaluation === null) {
            DB::transaction(fn () => $repository->update($content, [
                'status' => 'needs_review',
                'quality_warning' => true,
                'quality_warning_message' => 'Every iteration failed to generate — check provider/API logs.',
            ]));

            return;
        }

        // Phase 3: the only billed artwork in the whole run.
        $best = $renderer->render($this->contentUuid, $best, $this->data, $causer);

        $qualityWarning = ! $bestEvaluation->scores->allScoresPass;

        DB::transaction(fn () => $repository->update(
            $content,
            $this->persistablePackage($best, $bestEvaluation, $iterationsRan, $qualityWarning),
        ));

        $audit->log(
            event: 'social_media.ai.generation_completed',
            subject: $content,
            properties: [
                'iterations_required' => $iterationsRan,
                'all_scores_pass' => $bestEvaluation->scores->allScoresPass,
                'overall_score_avg' => $bestEvaluation->scores->overallAverage,
                'provider' => $this->data->provider,
                'evaluator_provider' => $bestEvaluation->evaluatorProvider,
            ],
            causer: $causer,
            logName: 'social_media',
        );

        $progress->notify(
            $causer,
            $this->contentUuid,
            'completed',
            $qualityWarning ? 'Best attempt saved for review.' : 'Content ready — all scores passed.',
            100,
            $iterationsRan,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function persistablePackage(
        GeneratedSocialMediaContentData $draft,
        ContentEvaluationData $evaluation,
        int $iterationsRan,
        bool $qualityWarning,
    ): array {
        return [
            'headline' => $draft->headline,
            'body' => $draft->body,
            'call_to_action' => $draft->callToAction,
            'hashtags' => $draft->hashtags,
            'platforms' => array_map(
                static fn (PlatformContentData $platform): array => $platform->toArray(),
                $draft->platforms,
            ),
            'cover_image_path' => $draft->coverImagePath,
            'cover_image_prompt' => $draft->coverImagePrompt,
            'scores' => $evaluation->scores->toArray(),
            'human_writing_index' => $evaluation->scores->humanWritingIndex->value,
            'virality_score' => $evaluation->scores->viralityScore->value,
            'engagement_score' => $evaluation->scores->engagementScore->value,
            'roi_score' => $evaluation->scores->roiScore->value,
            'trend_alignment' => $evaluation->scores->trendAlignment->value,
            'overall_score_avg' => $evaluation->scores->overallAverage,
            'all_scores_pass' => $evaluation->scores->allScoresPass,
            'iterations_required' => $iterationsRan,
            'quality_warning' => $qualityWarning,
            'quality_warning_message' => $qualityWarning
                ? 'Maximum iterations reached — showing the best attempt for manual review.'
                : null,
            'eeat_analysis' => $evaluation->eeatAnalysis,
            'optimization_suggestions' => $evaluation->optimizationSuggestions,
            'research_sources' => $draft->researchSources,
            'tavily_data_used' => $draft->tavilyDataUsed,
            'ai_detection_risk' => $evaluation->aiDetectionRisk,
            'status' => $qualityWarning ? 'needs_review' : 'ready',
        ];
    }

    /**
     * The loop owns 0-75% of the progress bar; the render phase owns the rest.
     * Reporting an iteration as a share of MAX_ITERATIONS alone used to hit
     * 100% before a single image existed.
     */
    private function loopProgress(int $iteration): int
    {
        return (int) round(($iteration / ContentQualityEvaluator::MAX_ITERATIONS) * 75);
    }

    /**
     * Terminal cleanup for a job the worker gave up on — a timeout kill, an
     * OOM, or an exception outside the per-iteration try/catch.
     *
     * Without this the row stays on `generating` permanently: `Tries(1)` means
     * there is no retry to finish the work, and the wizard polls
     * `ai/{uuid}/status` (or waits on the broadcast) for a completion event
     * that can never arrive. `needs_review` is the honest terminal state — the
     * user can see what happened and re-run generation.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('social_media.generation.job_failed', [
            'uuid' => $this->contentUuid,
            'error' => $exception?->getMessage() ?? 'unknown',
        ]);

        $repository = app(SocialMediaContentRepositoryPort::class);
        $content = $repository->findByUuid($this->contentUuid);

        if ($content === null || $content->status !== SocialMediaContentStatus::Generating) {
            return;
        }

        DB::transaction(fn () => $repository->update($content, [
            'status' => 'needs_review',
            'quality_warning' => true,
            'quality_warning_message' => 'Generation did not finish (timeout or worker failure). Re-run generation for this topic.',
        ]));
    }
}
