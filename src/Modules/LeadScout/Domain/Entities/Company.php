<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;
use Modules\LeadScout\Domain\Enums\EmployeeRange;

/**
 * Target company: identity, vitality and public legal-person data
 * (allowlist, spec FR-38). Never a natural person (spec FR-39).
 */
final readonly class Company
{
    /**
     * @param  list<string>  $aliases
     * @param  list<string>  $services
     * @param  list<string>  $sectors
     * @param  list<string>  $siteLanguages
     * @param  list<string>  $clientCompanies
     * @param  array<string, string>  $publicUrls
     * @param  list<array<string, mixed>>  $publicDataEvidence
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $canonicalDomain,
        public string $name,
        public ?string $country,
        public CompanyOrigin $origin,
        public ?string $originRef,
        public ?string $discoveryWave,
        public ?CompanyType $companyType,
        public EmployeeRange $employeeRange,
        public ?int $teamSizeObserved,
        public bool $hasDecisionMaker,
        public bool $needsResearch,
        public ActivityStatus $activityStatus,
        public ?DateTimeImmutable $lastActivityAt,
        public ?int $timezoneOverlapHours,
        public ?string $legalName,
        public ?string $legalForm,
        public ?string $taxId,
        public ?string $registryInfo,
        public ?string $city,
        public ?int $foundedYear,
        public array $aliases,
        public array $services,
        public array $sectors,
        public array $siteLanguages,
        public array $clientCompanies,
        public array $publicUrls,
        public array $publicDataEvidence,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {}
}
