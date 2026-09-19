<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\OpportunityStatus;
use Modules\LeadScout\Domain\Enums\OpportunityType;

/**
 * Revenue attached to an outreach (spec US-6 CA-2: hours and euros billed).
 */
final readonly class Opportunity
{
    public function __construct(
        public int $id,
        public string $uuid,
        public int $outreachId,
        public string $outreachUuid,
        public OpportunityType $type,
        public ?int $hoursPerMonth,
        public ?int $hourlyRateCents,
        public ?int $amountCents,
        public string $currency,
        public OpportunityStatus $status,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
    ) {}
}
