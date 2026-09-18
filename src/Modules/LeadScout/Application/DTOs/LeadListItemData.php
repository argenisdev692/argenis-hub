<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
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

    public static function fromModel(ScoutCompanyEloquentModel $company): self
    {
        $current = $company->relationLoaded('scoreResults')
            ? $company->scoreResults->firstWhere('is_current', true)
            : null;

        return new self(
            uuid: $company->uuid,
            name: $company->name,
            domain: $company->canonical_domain,
            country: $company->country,
            companyType: $company->company_type?->value,
            origin: $company->origin->value,
            discoveryWave: $company->discovery_wave,
            tier: $current?->tier->value,
            leadScore: $current?->lead_score,
            confidence: $current?->confidence,
            needsResearch: (bool) $company->needs_research,
            activityStatus: $company->activity_status->value,
            hasDecisionMaker: (bool) $company->has_decision_maker,
            updatedAt: $company->updated_at?->toIso8601String(),
        );
    }
}
