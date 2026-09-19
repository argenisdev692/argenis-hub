<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Enums\CompanyType;

/**
 * Company persistence (mandatory port in the intermediate baseline).
 */
interface CompanyRepositoryPort
{
    public function byUuid(string $uuid): ?Company;

    public function byDomain(string $domain): ?Company;

    public function byId(int $id): ?Company;

    /** A company that lists `$domain` among its alias domains. */
    public function byAlias(string $domain): ?Company;

    public function register(
        string $canonicalDomain,
        string $name,
        CompanyOrigin $origin,
        ?string $originRef,
        ?string $country = null,
        ?string $discoveryWave = null,
        ?int $timezoneOverlapHours = null,
    ): Company;

    /**
     * Public legal-person data from the allowlist (spec FR-38) and its evidence.
     *
     * @param  array<string, mixed>  $fields  allowlisted fields only
     * @param  array<string, array<string, mixed>>  $evidence
     */
    public function recordPublicData(Company $company, array $fields, array $evidence): void;

    public function recordDecisionMakers(Company $company, bool $hasDecisionMaker, ?int $teamSizeObserved): void;

    /**
     * Fills the classification; a null argument keeps the stored value.
     */
    public function recordClassification(Company $company, ?CompanyType $type, ?int $teamSizeObserved): void;

    public function setNeedsResearch(Company $company, bool $needsResearch): void;
}
