<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Entities\Source;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Source registry row (spec FR-2).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class SourceData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $country,
        public readonly string $accessMethod,
        public readonly int $frequencyMinutes,
        public readonly int $priority,
        public readonly string $status,
        public readonly ?string $lastRunAt,
        public readonly ?string $lastCursor,
        public readonly ?string $termsReviewedAt,
    ) {}

    public static function fromEntity(Source $source): self
    {
        return new self(
            uuid: $source->uuid,
            name: $source->name,
            type: $source->type->value,
            country: $source->country,
            accessMethod: $source->accessMethod,
            frequencyMinutes: $source->frequencyMinutes,
            priority: $source->priority,
            status: $source->status->value,
            lastRunAt: $source->lastRunAt?->format(DATE_ATOM),
            lastCursor: $source->lastCursor,
            termsReviewedAt: $source->termsReviewedAt?->format(DATE_ATOM),
        );
    }
}
