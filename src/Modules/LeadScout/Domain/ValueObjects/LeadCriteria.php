<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\Tier;

/**
 * Which leads the bandeja and its exports select (spec US-5, FR-21). A
 * null list means "no filter on that field". Date bounds are inclusive.
 */
final readonly class LeadCriteria
{
    /**
     * @param  list<Tier>|null  $tiers
     * @param  list<string>|null  $countries
     * @param  list<CompanyType>|null  $companyTypes
     * @param  list<SignalDimension>|null  $signalDimensions
     * @param  list<OutreachStage>|null  $stages
     * @param  list<CompanyOrigin>|null  $origins
     */
    public function __construct(
        public ?array $tiers = null,
        public ?array $countries = null,
        public ?array $companyTypes = null,
        public ?array $signalDimensions = null,
        public ?array $stages = null,
        public ?array $origins = null,
        public ?bool $needsResearch = null,
        public ?string $search = null,
        public ?DateTimeImmutable $createdFrom = null,
        public ?DateTimeImmutable $createdTo = null,
    ) {}
}
