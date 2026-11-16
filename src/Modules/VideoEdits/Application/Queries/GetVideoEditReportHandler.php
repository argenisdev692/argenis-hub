<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Queries;

use Modules\VideoEdits\Application\DTOs\AiRecommendationData;
use Modules\VideoEdits\Application\DTOs\AiReportDecisionData;
use Modules\VideoEdits\Application\DTOs\AiReportEditData;
use Modules\VideoEdits\Application\DTOs\AiReviewableCutData;
use Modules\VideoEdits\Application\DTOs\DurationPlanData;
use Modules\VideoEdits\Application\DTOs\VideoEditReportData;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;

/**
 * The AI decision report (US-14).
 *
 * Built entirely from stored data — decisions, recommendations, totals — so it
 * never reprocesses the video (EX-8). That is also why the report survives the
 * source files being deleted the moment the edit completed.
 */
final readonly class GetVideoEditReportHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private AiReportStorePort $reports,
        private AiCutReviewStorePort $reviews,
    ) {}

    /**
     * @throws VideoEditNotFoundException
     */
    #[\NoDiscard]
    public function handle(string $uuid, int $userId): VideoEditReportData
    {
        $edit = $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);

        $analysis = $this->reports->forEdit($edit->id);
        $review = $this->reviews->forEdit($edit->id);
        $approvedIds = $review?->approvedCutIds ?? [];

        return new VideoEditReportData(
            edit: AiReportEditData::fromModel($edit),
            // Only the AI's own decisions belong in an AI report; silence and
            // filler cuts are the edit summary's business, not this document's.
            decisions: $edit->cutDecisions
                ->where('origin', DecisionOrigin::Ai)
                ->map(AiReportDecisionData::fromModel(...))
                ->values()
                ->all(),
            recommendations: array_map(
                AiRecommendationData::fromValueObject(...),
                $analysis?->recommendations ?? [],
            ),
            conclusion: $analysis?->conclusion,
            durationPlan: $analysis?->durationPlan === null
                ? null
                : DurationPlanData::fromValueObject($analysis->durationPlan),
            keptPhrases: $analysis?->keptPhrases ?? [],
            reviewedCuts: array_map(
                static fn (AiReviewableCut $cut): AiReviewableCutData => AiReviewableCutData::fromCut($cut, in_array($cut->id, $approvedIds, true)),
                $review?->cuts ?? [],
            ),
            reviewIncludesAutomatic: $review?->includesAutomatic === true,
        );
    }
}
