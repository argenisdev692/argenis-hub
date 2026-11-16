<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The AI decision report (US-14) — one shape for the JSON response and the
 * PDF, so the two can never disagree.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoEditReportData extends Data
{
    /**
     * @param  list<AiReportDecisionData>  $decisions
     * @param  list<AiRecommendationData>  $recommendations
     * @param  list<AiReviewableCutData>  $reviewedCuts
     * @param  list<string>  $keptPhrases
     */
    public function __construct(
        public AiReportEditData $edit,
        public array $decisions,
        public array $recommendations,
        public ?string $conclusion,
        public ?DurationPlanData $durationPlan = null,
        public array $keptPhrases = [],
        /**
         * The same rows the review screen shows, in the same order: Start, End,
         * reason, what is removed and what remains. The PDF is the copy the
         * owner keeps, so a cut that was on screen and missing from it — or the
         * reverse — would make the document useless as a record.
         *
         * @var list<AiReviewableCutData>
         */
        public array $reviewedCuts = [],
        public bool $reviewIncludesAutomatic = false,
    ) {}
}
