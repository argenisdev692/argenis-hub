<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Support\Facades\Log;
use Modules\Post\Application\DTOs\GeneratedPostContentData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Application\DTOs\PostEvaluationData;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Exceptions\PostGenerationUnavailableException;
use Modules\Post\Domain\Ports\PostContentEvaluatorPort;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Broadcasting\PostGenerationProgressReporter;
use Modules\Post\Infrastructure\Queue\GeneratePostContentJob;
use RuntimeException;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * The quality loop, in three separately-priced phases:
 *
 *   1. WRITE + JUDGE, up to {@see PostContentQualityEvaluator::MAX_ITERATIONS}
 *      times — one text completion by the caller's provider, then one scoring
 *      pass by an independent model. Both are cheap, so a rejected attempt is
 *      cheap. Fresh Tavily research every iteration; the judge's explanations
 *      are fed back so the next attempt targets the scores that actually
 *      failed.
 *   2. PICK the best attempt (highest overall average, or the first that
 *      clears every threshold — the loop stops there).
 *   3. RENDER the cover, ONCE, for that winner only.
 *
 * Phase 3 used to sit inside phase 1's adapter call, and phase 2's scores came
 * from the writer itself. Both were wrong in the same direction: the writer
 * graded its own homework generously, so the loop exited early on inflated
 * numbers, and every attempt that DID run billed a cover that was thrown away.
 *
 * This is the ONLY implementation of the loop. It runs on the queue, driven by
 * {@see GeneratePostContentJob} — there is no parallel synchronous copy for
 * the two to drift apart, which is also why the job stays a thin shell that
 * calls this and records the outcome.
 *
 * Nothing about the POST is persisted here: the run writes only to its own
 * `post_ai_generations` row, and the user still reviews the draft and submits
 * the normal store/update route. Authorization (permission:CREATE_POSTS) is
 * enforced at the route that accepts the run; the meta-audit records who
 * triggered the (billed) generation.
 */
final readonly class GeneratePostContentHandler
{
    public function __construct(
        private PostContentGeneratorPort $writer,
        private PostContentEvaluatorPort $judge,
        private PostCoverImageRendererPort $renderer,
        private PostContentQualityEvaluator $evaluator,
        private PostGenerationProgressReporter $reporter,
        private AuditPort $audit,
    ) {}

    /**
     * `$generationUuid` is the row this run reports its live phase to. It is
     * nullable so the pipeline stays callable without one, but in practice the
     * only caller is {@see GeneratePostContentJob} and it always has one —
     * there is deliberately no second, synchronous implementation of this loop
     * to drift out of step with it.
     */
    public function handle(
        ?string $generationUuid,
        GeneratePostContentData $data,
        ?object $causer = null,
    ): GeneratedPostContentData {
        [$best, $bestEvaluation, $iterationsRan, $unavailable] = $this->runQualityLoop($generationUuid, $data, $causer);

        if ($best === null || $bestEvaluation === null) {
            // A down provider gets its own message: "try again in a moment" and
            // "every attempt produced an unusable draft" call for completely
            // different reactions from whoever reads it.
            throw $unavailable ?? new RuntimeException('Post content generation failed on every iteration.');
        }

        // Phase 3: the only billed artwork in the whole run.
        $cover = $this->renderer->render($generationUuid, $best, $data, $causer);

        $qualityWarning = ! $bestEvaluation->allScoresPass;

        $this->reporter->report(
            $generationUuid,
            $causer,
            PostAiGenerationStatus::Completed,
            $qualityWarning ? 'Best attempt ready for review.' : 'Draft ready — all scores passed.',
            100,
            $iterationsRan,
        );

        $draft = new GeneratedPostContentData(
            title: $best->title,
            content: $best->content,
            excerpt: $best->excerpt,
            metaTitle: $best->metaTitle,
            metaDescription: $best->metaDescription,
            metaKeywords: $best->metaKeywords,
            imageMode: $data->imageMode,
            coverImagePath: $cover->path,
            coverImageUrl: $cover->url,
            imagePrompts: $cover->prompts,
            provider: $data->provider,
            seoScore: $bestEvaluation->scores['seo_score'],
            eeatScore: $bestEvaluation->scores['eeat_score'],
            viralityScore: $bestEvaluation->scores['virality_score'],
            roiScore: $bestEvaluation->scores['roi_score'],
            humanWritingIndex: $bestEvaluation->scores['human_writing_index'],
            aiDetectionRisk: $bestEvaluation->aiDetectionRisk,
            allScoresPass: $bestEvaluation->allScoresPass,
            iterationsRequired: $iterationsRan,
            qualityWarning: $qualityWarning,
            // "Maximum iterations reached" is only true when the loop actually
            // ran out of attempts. A run cut short by a down provider has to
            // say so, or the reader goes looking for a quality problem that
            // isn't there.
            qualityWarningMessage: match (true) {
                ! $qualityWarning => null,
                $unavailable !== null => 'The AI provider became unavailable mid-run — showing the best attempt made before it did.',
                default => 'Maximum iterations reached — showing the best attempt for manual review.',
            },
            overallScoreAvg: $bestEvaluation->overallAverage,
            scores: $bestEvaluation->scores,
            eeatAnalysis: $bestEvaluation->eeatAnalysis,
            optimizationSuggestions: $bestEvaluation->optimizationSuggestions,
            seoAnalysis: $best->seoAnalysis,
            evaluatorProvider: $bestEvaluation->evaluatorProvider,
        );

        $this->audit->log(
            event: 'post.ai.content_generated',
            properties: [
                'provider' => $data->provider,
                'evaluator_provider' => $bestEvaluation->evaluatorProvider,
                'topic' => $data->topic,
                'image_mode' => $data->imageMode->value,
                'cover_rendered' => $cover->path !== null,
                'seo_score' => $draft->seoScore,
                'eeat_score' => $draft->eeatScore,
                'virality_score' => $draft->viralityScore,
                'roi_score' => $draft->roiScore,
                'human_writing_index' => $draft->humanWritingIndex,
                'overall_score_avg' => $draft->overallScoreAvg,
                'all_scores_pass' => $draft->allScoresPass,
                'iterations_required' => $draft->iterationsRequired,
                'quality_warning' => $draft->qualityWarning,
            ],
            causer: $causer,
            logName: 'post',
        );

        return $draft;
    }

    /**
     * Phases 1 and 2.
     *
     * A provider HICCUP on one iteration is survivable — it is logged and the
     * loop moves on, so a single 429 does not lose the four attempts that would
     * have followed it. A provider that is DOWN is not: an open circuit breaker
     * rejects every further call locally, without leaving the process, so the
     * remaining iterations can only add log noise before ending on a message
     * that explains nothing. That case stops the loop and is reported as
     * itself.
     *
     * Stopping is not the same as failing. If an earlier iteration already
     * produced a scored draft, that draft is still the best answer available
     * and the run completes with it — losing finished work because the NEXT
     * attempt could not start would be strictly worse.
     *
     * @return array{0: ?PostContentDraftData, 1: ?PostEvaluationData, 2: int, 3: ?PostGenerationUnavailableException}
     */
    private function runQualityLoop(?string $generationUuid, GeneratePostContentData $data, ?object $causer): array
    {
        $best = null;
        $bestEvaluation = null;
        $previousWeaknesses = [];
        $iterationsRan = 0;
        $unavailable = null;

        for ($iteration = 1; $iteration <= PostContentQualityEvaluator::MAX_ITERATIONS; $iteration++) {
            try {
                $draft = $this->writer->generate($generationUuid, $data, $iteration, $previousWeaknesses, $causer);
                $evaluation = $this->judge->evaluate($generationUuid, $draft, $data, $iteration, $causer);
            } catch (PostGenerationUnavailableException $exception) {
                Log::warning('post.ai.generation.provider_unavailable', [
                    'iteration' => $iteration,
                    'error' => $exception->getMessage(),
                ]);

                $unavailable = $exception;

                break;
            } catch (Throwable $exception) {
                // Counted: this attempt reached the provider and came back bad.
                // The breaker case above deliberately is not — it never left.
                $iterationsRan = $iteration;

                Log::warning('post.ai.generation.iteration_failed', [
                    'iteration' => $iteration,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            $iterationsRan = $iteration;

            if ($bestEvaluation === null || $evaluation->overallAverage > $bestEvaluation->overallAverage) {
                $best = $draft;
                $bestEvaluation = $evaluation;
            }

            if ($evaluation->allScoresPass) {
                break;
            }

            $previousWeaknesses = $this->evaluator->identifyWeaknesses(
                $evaluation->scores,
                $evaluation->explanations,
            );
        }

        return [$best, $bestEvaluation, $iterationsRan, $unavailable];
    }
}
