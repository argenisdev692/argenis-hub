<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\OpportunityData;
use Modules\LeadScout\Application\DTOs\UpsertOpportunityData;
use Modules\LeadScout\Domain\Enums\OpportunityStatus;
use Modules\LeadScout\Domain\Enums\OpportunityType;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

/**
 * Opportunity upsert (spec US-6, T065): hours and euros ride the contact,
 * several per outreach. Returned with the parent uuid for cache keys.
 *
 * @return array{opportunity: ScoutOpportunityEloquentModel, outreach_uuid: string}
 */
final readonly class UpsertOpportunityHandler
{
    public function handleForOutreach(string $outreachUuid, UpsertOpportunityData $data): array
    {
        $outreach = ScoutOutreachEloquentModel::query()->where('uuid', $outreachUuid)->first();

        if ($outreach === null) {
            throw ValidationException::withMessages(['outreach' => 'Outreach not found.']);
        }

        $opportunity = DB::transaction(fn (): ScoutOpportunityEloquentModel => ScoutOpportunityEloquentModel::query()->create([
            'outreach_id' => $outreach->id,
            'type' => $data->type ?? OpportunityType::Trial->value,
            'hours_per_month' => $data->hoursPerMonth,
            'hourly_rate_cents' => $data->hourlyRateCents,
            'amount_cents' => $data->amountCents,
            'currency' => $data->currency ?? 'EUR',
            'status' => $data->status ?? OpportunityStatus::Open->value,
            'started_at' => $data->startedAt,
            'ended_at' => $data->endedAt,
        ]));

        return ['opportunity' => $opportunity, 'outreach_uuid' => $outreach->uuid];
    }

    /**
     * @return array{opportunity: ScoutOpportunityEloquentModel, outreach_uuid: string}
     */
    public function handleUpdate(string $opportunityUuid, UpsertOpportunityData $data): array
    {
        $opportunity = ScoutOpportunityEloquentModel::query()->with('outreach')->where('uuid', $opportunityUuid)->first();

        if ($opportunity === null) {
            throw ValidationException::withMessages(['opportunity' => 'Opportunity not found.']);
        }

        DB::transaction(function () use ($opportunity, $data): void {
            $opportunity->update(array_filter([
                'type' => $data->type,
                'hours_per_month' => $data->hoursPerMonth,
                'hourly_rate_cents' => $data->hourlyRateCents,
                'amount_cents' => $data->amountCents,
                'currency' => $data->currency,
                'status' => $data->status,
                'started_at' => $data->startedAt,
                'ended_at' => $data->endedAt,
            ], static fn (mixed $value): bool => $value !== null));
        });

        return ['opportunity' => $opportunity->refresh(), 'outreach_uuid' => $opportunity->outreach->uuid];
    }

    public static function toData(ScoutOpportunityEloquentModel $opportunity, string $outreachUuid): OpportunityData
    {
        return OpportunityData::fromModel($opportunity, $outreachUuid);
    }
}
