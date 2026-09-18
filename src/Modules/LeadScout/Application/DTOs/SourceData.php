<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
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

    public static function fromModel(ScoutSourceEloquentModel $source): self
    {
        return new self(
            uuid: $source->uuid,
            name: $source->name,
            type: $source->type->value,
            country: $source->country,
            accessMethod: $source->access_method,
            frequencyMinutes: $source->frequency_minutes,
            priority: $source->priority,
            status: $source->status->value,
            lastRunAt: $source->last_run_at?->toIso8601String(),
            lastCursor: $source->last_cursor,
            termsReviewedAt: $source->terms_reviewed_at?->toIso8601String(),
        );
    }
}
