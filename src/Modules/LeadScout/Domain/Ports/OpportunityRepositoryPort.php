<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\Opportunity;
use Modules\LeadScout\Domain\ValueObjects\OpportunityTerms;

interface OpportunityRepositoryPort
{
    public function byUuid(string $uuid): ?Opportunity;

    public function create(int $outreachId, OpportunityTerms $terms): Opportunity;

    /**
     * Applies the non-null terms; the rest keeps its stored value.
     */
    public function revise(Opportunity $opportunity, OpportunityTerms $changes): Opportunity;
}
