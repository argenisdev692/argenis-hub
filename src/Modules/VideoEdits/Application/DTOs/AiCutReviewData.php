<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use DateTimeInterface;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The AI cut review of an edit: what was proposed and, once answered, what the
 * owner approved.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AiCutReviewData extends Data
{
    /**
     * @param  list<AiReviewableCutData>  $cuts
     * @param  list<string>  $approvedCutIds
     */
    public function __construct(
        #[DataCollectionOf(AiReviewableCutData::class)]
        public array $cuts,
        public bool $isResolved,
        public ?string $reviewedAt,
        public array $approvedCutIds,
        public bool $resolvedByExpiry,
    ) {}

    public static function fromReview(AiCutReview $review): self
    {
        return new self(
            cuts: array_map(
                static fn (AiReviewableCut $cut): AiReviewableCutData => AiReviewableCutData::fromCut($cut),
                $review->cuts,
            ),
            isResolved: $review->isResolved(),
            reviewedAt: $review->reviewedAt?->format(DateTimeInterface::ATOM),
            approvedCutIds: $review->approvedCutIds,
            resolvedByExpiry: $review->resolvedByExpiry,
        );
    }
}
