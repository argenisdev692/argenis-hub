<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\UpsertOpportunityData;
use Modules\LeadScout\Domain\Entities\Opportunity;
use Modules\LeadScout\Domain\Enums\OpportunityStatus;
use Modules\LeadScout\Domain\Enums\OpportunityType;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Ports\OpportunityRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\OpportunityTerms;

/**
 * Opportunity upsert (spec US-6, T065): hours and euros ride the contact,
 * several per outreach.
 */
final readonly class UpsertOpportunityHandler
{
    public function __construct(
        private OutreachRepositoryPort $outreaches,
        private OpportunityRepositoryPort $opportunities,
    ) {}

    public function handleForOutreach(string $outreachUuid, UpsertOpportunityData $data): Opportunity
    {
        $outreach = $this->outreaches->byUuid($outreachUuid)
            ?? throw InvalidInputException::withMessages(['outreach' => 'Outreach not found.']);

        return $this->opportunities->create($outreach->id, new OpportunityTerms(
            type: $data->type === null ? OpportunityType::Trial : OpportunityType::from($data->type),
            hoursPerMonth: $data->hoursPerMonth,
            hourlyRateCents: $data->hourlyRateCents,
            amountCents: $data->amountCents,
            currency: $data->currency ?? 'EUR',
            status: $data->status === null ? OpportunityStatus::Open : OpportunityStatus::from($data->status),
            startedAt: $data->startedAt,
            endedAt: $data->endedAt,
        ));
    }

    public function handleUpdate(string $opportunityUuid, UpsertOpportunityData $data): Opportunity
    {
        $opportunity = $this->opportunities->byUuid($opportunityUuid)
            ?? throw InvalidInputException::withMessages(['opportunity' => 'Opportunity not found.']);

        return $this->opportunities->revise($opportunity, new OpportunityTerms(
            type: $data->type === null ? null : OpportunityType::from($data->type),
            hoursPerMonth: $data->hoursPerMonth,
            hourlyRateCents: $data->hourlyRateCents,
            amountCents: $data->amountCents,
            currency: $data->currency,
            status: $data->status === null ? null : OpportunityStatus::from($data->status),
            startedAt: $data->startedAt,
            endedAt: $data->endedAt,
        ));
    }
}
