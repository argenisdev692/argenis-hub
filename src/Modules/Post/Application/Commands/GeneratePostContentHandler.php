<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Illuminate\Support\Facades\Log;
use Modules\Post\Application\DTOs\GeneratedPostContentData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Application\DTOs\PostEvaluationData;
use Modules\Post\Domain\Ports\PostContentEvaluatorPort;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Broadcasting\PostProgressNotifier;
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
 * Imperative AI action — nothing is persisted here. The user reviews the draft
 * and submits the normal store/update route. Authorization
 * (permission:CREATE_POSTS) is enforced at the route; the meta-audit records
 * who triggered the (billed) generation.
 */
final readonly class GeneratePostContentHandler
{
    public function __construct(
        private PostContentGeneratorPort $writer,
        private PostContentEvaluatorPort $judge,
        private PostCoverImageRendererPort $renderer,
        private PostContentQualityEvaluator $evaluator,
        private PostProgressNotifier $progress,
        private AuditPort $audit,
    ) {}

    public function handle(GeneratePostContentData $data, ?object $causer = null): GeneratedPostContentData
    {
        [$best, $bestEvaluation, $iterationsRan] = $this->runQualityLoop($data, $causer);

        if ($best === null || $bestEvaluation === null) {
            throw new RuntimeException('Post content generation failed on every iteration.');
        }

        // Phase 3: the only billed artwork in the whole run.
        $cover = $this->renderer->render($best, $data, $causer);

        $qualityWarning = ! $bestEvaluation->allScoresPass;

        $this->progress->notify(
            $causer,
            'content',
            'done',
            $qualityWarning ? 'Best attempt ready for review.' : 'Draft ready — all scores passed.',
            100,
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
            qualityWarningMessage: $qualityWarning
                ? 'Maximum iterations reached — showing the best attempt for manual review.'
                : null,
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
     * Phases 1 and 2. A provider hiccup on one iteration is survivable — it is
     * logged and the loop moves on, so a single 429 does not lose the four
     * attempts that would have followed it.
     *
     * @return array{0: ?PostContentDraftData, 1: ?PostEvaluationData, 2: int}
     */
    private function runQualityLoop(GeneratePostContentData $data, ?object $causer): array
    {
        $best = null;
        $bestEvaluation = null;
        $previousWeaknesses = [];
        $iterationsRan = 0;

        for ($iteration = 1; $iteration <= PostContentQualityEvaluator::MAX_ITERATIONS; $iteration++) {
            $iterationsRan = $iteration;

            try {
                $draft = $this->writer->generate($data, $iteration, $previousWeaknesses, $causer);
                $evaluation = $this->judge->evaluate($draft, $data, $iteration, $causer);
            } catch (Throwable $exception) {
                Log::warning('post.ai.generation.iteration_failed', [
                    'iteration' => $iteration,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

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

        return [$best, $bestEvaluation, $iterationsRan];
    }
}
