<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\Log;
use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\CampaignEvaluationData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Application\DTOs\GeneratedCampaignData;
use Modules\Campaigns\Domain\Exceptions\CampaignGenerationUnavailableException;
use Modules\Campaigns\Domain\Ports\CampaignAssetRendererPort;
use Modules\Campaigns\Domain\Ports\CampaignEvaluatorPort;
use Modules\Campaigns\Domain\Ports\CampaignGeneratorPort;
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;
use Modules\Campaigns\Infrastructure\Broadcasting\CampaignProgressNotifier;
use Modules\Campaigns\Infrastructure\Queue\GenerateCampaignJob;
use RuntimeException;
use Throwable;

/**
 * The quality loop, in three separately-priced phases:
 *
 *   1. WRITE + JUDGE, up to {@see CampaignQualityEvaluator::MAX_ITERATIONS}
 *      times — one text completion by the caller's provider, then one scoring
 *      pass by an independent model. Both are cheap, so a rejected attempt is
 *      cheap. Fresh Tavily research every iteration; the judge's explanations
 *      are fed back so the next attempt targets the scores that actually
 *      failed.
 *   2. PICK the best attempt (highest overall average, or the first that
 *      clears every threshold — the loop stops there).
 *   3. RENDER the artwork and the Reels voiceover, ONCE, for that winner only.
 *
 * Phase 3 used to sit inside phase 1's adapter call, and phase 2's scores came
 * from the writer itself. Both were wrong in the same direction: the writer
 * graded its own homework generously, so the loop exited early on inflated
 * numbers, and every attempt that DID run billed a cover plus one image per
 * platform variant that was thrown away — up to 15 images to keep 3.
 *
 * This is the ONLY implementation of the loop. It runs on the queue, driven by
 * {@see GenerateCampaignJob} — there is no parallel synchronous copy for the
 * two to drift apart, which is also why that job stays a thin shell that calls
 * this and persists the outcome.
 */
final readonly class RunCampaignGenerationHandler
{
    public function __construct(
        private CampaignGeneratorPort $writer,
        private CampaignEvaluatorPort $judge,
        private CampaignAssetRendererPort $renderer,
        private CampaignQualityEvaluator $evaluator,
        private CampaignProgressNotifier $progress,
    ) {}

    #[\NoDiscard]
    public function handle(
        string $campaignUuid,
        GenerateCampaignData $data,
        ?object $causer = null,
    ): GeneratedCampaignData {
        [$best, $bestEvaluation, $iterationsRan, $unavailable] = $this->runQualityLoop($campaignUuid, $data, $causer);

        if ($best === null || $bestEvaluation === null) {
            // A down provider gets its own message: "try again in a moment" and
            // "every attempt produced an unusable ad" call for completely
            // different reactions from whoever reads it.
            throw $unavailable ?? new RuntimeException('Campaign generation failed on every iteration.');
        }

        // Phase 3: the only billed artwork and the only TTS call in the run.
        $rendered = $this->renderer->render($campaignUuid, $best, $data, $causer);

        $qualityWarning = ! $bestEvaluation->scores->allScoresPass;

        $this->progress->notify(
            $causer,
            $campaignUuid,
            'completed',
            $qualityWarning ? 'Best attempt saved for review.' : 'Campaign ready — all scores passed.',
            100,
            $iterationsRan,
        );

        return GeneratedCampaignData::fromRun(
            draft: $rendered,
            evaluation: $bestEvaluation,
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
        );
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
     * @return array{0: ?CampaignDraftData, 1: ?CampaignEvaluationData, 2: int, 3: ?CampaignGenerationUnavailableException}
     */
    private function runQualityLoop(string $campaignUuid, GenerateCampaignData $data, ?object $causer): array
    {
        $best = null;
        $bestEvaluation = null;
        $previousWeaknesses = [];
        $iterationsRan = 0;
        $unavailable = null;

        for ($iteration = 1; $iteration <= CampaignQualityEvaluator::MAX_ITERATIONS; $iteration++) {
            try {
                $draft = $this->writer->generate($campaignUuid, $data, $iteration, $previousWeaknesses, $causer);
                $evaluation = $this->judge->evaluate($campaignUuid, $draft, $data, $iteration, $causer);
            } catch (CampaignGenerationUnavailableException $exception) {
                Log::warning('campaigns.ai.generation.provider_unavailable', [
                    'uuid' => $campaignUuid,
                    'iteration' => $iteration,
                    'error' => $exception->getMessage(),
                ]);

                $unavailable = $exception;

                break;
            } catch (Throwable $exception) {
                // Counted: this attempt reached the provider and came back bad.
                // The breaker case above deliberately is not — it never left.
                $iterationsRan = $iteration;

                Log::warning('campaigns.ai.generation.iteration_failed', [
                    'uuid' => $campaignUuid,
                    'iteration' => $iteration,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            $iterationsRan = $iteration;

            if ($bestEvaluation === null || $evaluation->scores->overallAverage > $bestEvaluation->scores->overallAverage) {
                $best = $draft;
                $bestEvaluation = $evaluation;
            }

            if ($evaluation->scores->allScoresPass) {
                break;
            }

            $previousWeaknesses = $this->evaluator->identifyWeaknesses(
                $evaluation->scores->toThresholdMap(),
                $evaluation->explanations,
            );
        }

        return [$best, $bestEvaluation, $iterationsRan, $unavailable];
    }
}
