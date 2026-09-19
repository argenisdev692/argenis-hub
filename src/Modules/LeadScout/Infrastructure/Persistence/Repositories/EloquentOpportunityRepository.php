<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Modules\LeadScout\Domain\Entities\Opportunity;
use Modules\LeadScout\Domain\Ports\OpportunityRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\OpportunityTerms;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\OpportunityMapper;

final readonly class EloquentOpportunityRepository implements OpportunityRepositoryPort
{
    public function byUuid(string $uuid): ?Opportunity
    {
        $model = ScoutOpportunityEloquentModel::query()->with('outreach:id,uuid')->where('uuid', $uuid)->first();

        return $model === null ? null : OpportunityMapper::toEntity($model, $model->outreach->uuid);
    }

    public function create(int $outreachId, OpportunityTerms $terms): Opportunity
    {
        $model = ScoutOpportunityEloquentModel::query()->create([
            'outreach_id' => $outreachId,
            ...self::columns($terms),
        ]);

        return OpportunityMapper::toEntity($model, $model->load('outreach:id,uuid')->outreach->uuid);
    }

    public function revise(Opportunity $opportunity, OpportunityTerms $changes): Opportunity
    {
        $model = ScoutOpportunityEloquentModel::query()->findOrFail($opportunity->id);
        $model->update(self::columns($changes));

        return OpportunityMapper::toEntity($model->refresh(), $opportunity->outreachUuid);
    }

    /**
     * Non-null terms only.
     *
     * @return array<string, mixed>
     */
    private static function columns(OpportunityTerms $terms): array
    {
        return array_filter([
            'type' => $terms->type?->value,
            'hours_per_month' => $terms->hoursPerMonth,
            'hourly_rate_cents' => $terms->hourlyRateCents,
            'amount_cents' => $terms->amountCents,
            'currency' => $terms->currency,
            'status' => $terms->status?->value,
            'started_at' => $terms->startedAt,
            'ended_at' => $terms->endedAt,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
