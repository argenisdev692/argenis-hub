<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Modules\LeadScout\Domain\Enums\OpportunityStatus;
use Modules\LeadScout\Domain\Enums\OpportunityType;

/**
 * Opportunity fields as entered by the operator. On a revision, a null
 * field keeps the stored value.
 */
final readonly class OpportunityTerms
{
    public function __construct(
        public ?OpportunityType $type = null,
        public ?int $hoursPerMonth = null,
        public ?int $hourlyRateCents = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?OpportunityStatus $status = null,
        public ?string $startedAt = null,
        public ?string $endedAt = null,
    ) {}
}
