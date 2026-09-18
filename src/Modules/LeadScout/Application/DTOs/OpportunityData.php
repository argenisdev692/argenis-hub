<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Opportunity response (plan §5 `OpportunityData`).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class OpportunityData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $outreachUuid,
        public readonly string $type,
        public readonly ?int $hoursPerMonth,
        public readonly ?int $hourlyRateCents,
        public readonly ?int $amountCents,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $startedAt,
        public readonly ?string $endedAt,
    ) {}

    public static function fromModel(ScoutOpportunityEloquentModel $opportunity, string $outreachUuid): self
    {
        return new self(
            uuid: $opportunity->uuid,
            outreachUuid: $outreachUuid,
            type: $opportunity->type->value,
            hoursPerMonth: $opportunity->hours_per_month,
            hourlyRateCents: $opportunity->hourly_rate_cents,
            amountCents: $opportunity->amount_cents,
            currency: $opportunity->currency,
            status: $opportunity->status->value,
            startedAt: $opportunity->started_at?->toDateString(),
            endedAt: $opportunity->ended_at?->toDateString(),
        );
    }
}
