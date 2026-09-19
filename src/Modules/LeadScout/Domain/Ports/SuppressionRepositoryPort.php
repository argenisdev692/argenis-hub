<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\Suppression;
use Modules\LeadScout\Domain\Enums\SuppressionSource;

interface SuppressionRepositoryPort
{
    /**
     * Candidates for an exact-match decision in `SuppressionGate`: any entry
     * sharing the domain, the tax ID or the name.
     *
     * @return list<Suppression>
     */
    public function matching(?string $domain, ?string $taxId, ?string $name): array;

    /**
     * Suppresses a domain once; an existing entry for it is returned as is.
     */
    public function suppressDomain(string $domain, SuppressionSource $source, string $reason): Suppression;

    /**
     * Stores a DGC opposition-list row, merging into an entry that already
     * matches its tax ID, domain or name.
     *
     * @return bool true when a new entry was created
     */
    public function recordDgcListing(?string $taxId, ?string $domain, ?string $name, string $period, string $reason): bool;

    public function isDgcListed(string $domain, ?string $taxId, string $name): bool;

    public function latestDgcImportAt(): ?DateTimeImmutable;
}
