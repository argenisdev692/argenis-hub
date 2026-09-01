<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Modules\Campaigns\Domain\Ports\CampaignEvaluatorPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The independent judge's verdict on one {@see CampaignDraftData}, produced by
 * {@see CampaignEvaluatorPort}.
 *
 * `optimizationSuggestions` and `aiDetectionRisk` live here rather than on the
 * draft on purpose: both are judgements ABOUT the copy, and asking the writer
 * to volunteer its own weaknesses produced exactly the answer you would
 * expect.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignEvaluationData extends Data
{
    /**
     * @param  array<string, string>  $explanations  score key => why it scored that way
     * @param  list<string>  $optimizationSuggestions
     * @param  array{value: int, label: string, explanation: string}  $aiDetectionRisk
     */
    public function __construct(
        public CampaignScoreSetData $scores,
        public array $explanations,
        public array $optimizationSuggestions,
        public array $aiDetectionRisk,
        public string $evaluatorProvider,
    ) {}
}
