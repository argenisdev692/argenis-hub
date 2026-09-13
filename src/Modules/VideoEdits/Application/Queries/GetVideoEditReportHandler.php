<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Queries;

use Modules\VideoEdits\Application\DTOs\AiRecommendationData;
use Modules\VideoEdits\Application\DTOs\AiReportDecisionData;
use Modules\VideoEdits\Application\DTOs\AiReportEditData;
use Modules\VideoEdits\Application\DTOs\VideoEditReportData;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;

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
        );
    }
}
