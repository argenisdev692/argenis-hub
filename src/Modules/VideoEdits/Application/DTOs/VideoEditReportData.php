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
     */
    public function __construct(
        public AiReportEditData $edit,
        public array $decisions,
        public array $recommendations,
        public ?string $conclusion,
    ) {}
}
