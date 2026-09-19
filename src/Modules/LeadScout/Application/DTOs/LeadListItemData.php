<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Enums\Tier;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Bandeja row (plan §5 `LeadListItemData`): company + current score only.
 * Counts stay out — aggregates ride `withCount`, never loops (N+1 rule).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class LeadListItemData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $domain,
        public readonly ?string $country,
        public readonly ?string $companyType,
        public readonly string $origin,
        public readonly ?string $discoveryWave,
        public readonly ?string $tier,
        public readonly ?int $leadScore,
        public readonly ?int $confidence,
        public readonly bool $needsResearch,
        public readonly string $activityStatus,
        public readonly bool $hasDecisionMaker,
        public readonly ?string $updatedAt,
    ) {}

    /**
     * @param  array{company: Company, tier: ?Tier, leadScore: ?int, confidence: ?int}  $lead
     */
    public static function fromLead(array $lead): self
    {
        $company = $lead['company'];

        return new self(
            uuid: $company->uuid,
            name: $company->name,
            domain: $company->canonicalDomain,
            country: $company->country,
            companyType: $company->companyType?->value,
            origin: $company->origin->value,
            discoveryWave: $company->discoveryWave,
            tier: $lead['tier']?->value,
            leadScore: $lead['leadScore'],
            confidence: $lead['confidence'],
            needsResearch: $company->needsResearch,
            activityStatus: $company->activityStatus->value,
            hasDecisionMaker: $company->hasDecisionMaker,
            updatedAt: $company->updatedAt?->format(DATE_ATOM),
        );
    }
}
