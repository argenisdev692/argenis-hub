<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Enums\Tier;

/**
 * One page of the prioritized bandeja (spec US-5).
 */
final readonly class LeadPage
{
    /**
     * @param  list<array{company: Company, tier: ?Tier, leadScore: ?int, confidence: ?int}>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $perPage,
        public int $total,
    ) {}
}
