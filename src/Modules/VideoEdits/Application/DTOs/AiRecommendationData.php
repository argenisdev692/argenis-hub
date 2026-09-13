<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * An editorial finding the AI reported but never applied as a cut (R6/R7).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AiRecommendationData extends Data
{
    public function __construct(
        public AiRecommendationKind $kind,
        public string $title,
        public string $detail,
        public ?int $startMs,
        public ?int $endMs,
    ) {}

    public static function fromValueObject(AiRecommendation $recommendation): self
    {
        return new self(
            kind: $recommendation->kind,
            title: $recommendation->title,
            detail: $recommendation->detail,
            startMs: $recommendation->startMs,
            endMs: $recommendation->endMs,
        );
    }
}
