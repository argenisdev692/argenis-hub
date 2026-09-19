<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Queries;

use Modules\LeadScout\Application\DTOs\LeadFilterData;
use Modules\LeadScout\Domain\Ports\LeadReadRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\LeadPage;

/**
 * Prioritized bandeja read (spec US-5, T060). Discarded leads read through
 * the same filter with `tier[]=discarded` + reason.
 */
final readonly class ListLeadsHandler
{
    public function __construct(private LeadReadRepositoryPort $leads) {}

    #[\NoDiscard]
    public function handle(LeadFilterData $filters, int $perPage = 15): LeadPage
    {
        return $this->leads->page($filters->toCriteria(), $perPage);
    }
}
