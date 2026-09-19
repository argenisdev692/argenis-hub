<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Opportunity;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;

final readonly class OpportunityMapper
{
    public static function toEntity(ScoutOpportunityEloquentModel $model, string $outreachUuid): Opportunity
    {
        return new Opportunity(
            id: $model->id,
            uuid: $model->uuid,
            outreachId: (int) $model->outreach_id,
            outreachUuid: $outreachUuid,
            type: $model->type,
            hoursPerMonth: $model->hours_per_month,
            hourlyRateCents: $model->hourly_rate_cents,
            amountCents: $model->amount_cents,
            currency: (string) $model->currency,
            status: $model->status,
            startedAt: $model->started_at?->toDateTimeImmutable(),
            endedAt: $model->ended_at?->toDateTimeImmutable(),
        );
    }
}
