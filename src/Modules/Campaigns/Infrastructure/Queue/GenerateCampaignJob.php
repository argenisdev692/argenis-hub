<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Queue;

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
use Modules\Campaigns\Application\Commands\RunCampaignGenerationHandler;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Application\DTOs\PlatformCampaignContentData;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * Thin queue shell around {@see RunCampaignGenerationHandler}: it owns the
 * transaction, the persistence mapping and the audit entry, and nothing else.
 * The loop itself lives in Application so there is exactly one implementation
 * of it.
 *
 * Queued rather than synchronous because a full run can mean up to
 * {@see CampaignQualityEvaluator::MAX_ITERATIONS}x (Tavily + write + judge)
 * plus one render pass, well past what a single HTTP request should block on.
 *
 * Dependencies are method-injected on {@see self::handle()} (not constructor
 * promotion) — the job is serialized onto the Redis queue, so only plain
 * scalars/DTOs belong on `$this`.
 */
#[Queue('default')]
#[Tries(1)]
#[Timeout(300)]
final class GenerateCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $campaignUuid,
        private readonly GenerateCampaignData $data,
        private readonly ?int $causerId = null,
    ) {}

    public function handle(
        RunCampaignGenerationHandler $run,
        CampaignRepositoryPort $repository,
        AuditPort $audit,
    ): void {
        $campaign = $repository->findByUuid($this->campaignUuid);

        if ($campaign === null) {
            Log::warning('campaigns.generation.campaign_missing', ['uuid' => $this->campaignUuid]);

            return;
        }

        $causer = $this->causerId !== null ? User::find($this->causerId) : null;

        try {
            $result = $run->handle($this->campaignUuid, $this->data, $causer);
        } catch (Throwable $exception) {
            Log::warning('campaigns.generation.run_failed', [
                'uuid' => $this->campaignUuid,
                'error' => $exception->getMessage(),
            ]);

            DB::transaction(fn () => $repository->update($campaign, [
                'status' => 'needs_review',
                'quality_warning' => true,
                'quality_warning_message' => $exception->getMessage(),
            ]));

            return;
        }

        DB::transaction(fn () => $repository->update($campaign, [
            'headline' => $result->headline,
            'primary_text' => $result->primaryText,
            'description' => $result->description,
            'call_to_action' => $result->callToAction,
            'hashtags' => $result->hashtags,
            'lead_form_questions' => $result->leadFormQuestions,
            'targeting_suggestions' => $result->targetingSuggestions,
            'platforms' => array_map(
                static fn (PlatformCampaignContentData $platform): array => $platform->toArray(),
                $result->platforms,
            ),
            'cover_image_path' => $result->coverImagePath,
            'cover_image_prompt' => $result->coverImagePrompt,
            'scores' => $result->scores->toArray(),
            'audience_fit_score' => $result->scores->audienceFitScore->value,
            'virality_score' => $result->scores->viralityScore->value,
            'roi_potential_score' => $result->scores->roiPotentialScore->value,
            'lead_quality_score' => $result->scores->leadQualityScore->value,
            'trend_relevance_score' => $result->scores->trendRelevanceScore->value,
            'overall_score_avg' => $result->scores->overallAverage,
            'success_probability_label' => $result->scores->successProbabilityLabel,
            'all_scores_pass' => $result->scores->allScoresPass,
            'iterations_required' => $result->iterationsRequired,
            'quality_warning' => $result->qualityWarning,
            'quality_warning_message' => $result->qualityWarningMessage,
            'optimization_suggestions' => $result->optimizationSuggestions,
            'research_sources' => $result->researchSources,
            'tavily_data_used' => $result->tavilyDataUsed,
            'ai_detection_risk' => $result->aiDetectionRisk,
            'status' => $result->qualityWarning ? 'needs_review' : 'ready',
        ]));

        $audit->log(
            event: 'campaigns.ai.generation_completed',
            subject: $campaign,
            properties: [
                'iterations_required' => $result->iterationsRequired,
                'all_scores_pass' => $result->scores->allScoresPass,
                'overall_score_avg' => $result->scores->overallAverage,
                'success_probability_label' => $result->scores->successProbabilityLabel,
                'provider' => $result->provider,
                'evaluator_provider' => $result->evaluatorProvider,
                'cover_rendered' => $result->coverImagePath !== null,
            ],
            causer: $causer,
            logName: 'campaigns',
        );
    }
}
