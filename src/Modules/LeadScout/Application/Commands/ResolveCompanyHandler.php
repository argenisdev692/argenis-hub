<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Entities\JobPosting;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Ports\SearchPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Domain\ValueObjects\SearchQuery;

/**
 * Links a posting to its company (spec US-3, T043): payload domain first
 * (free), then ONE cached basic search ("{company} {country} official
 * site"), then `unresolved` — which spends nothing further (US-3 CA-1).
 * Alias domains consolidate into the existing company (US-3 CA-4);
 * suppressed companies resolve to null without enrichment.
 */
final readonly class ResolveCompanyHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private JobPostingRepositoryPort $postings,
        private SearchPort $search,
        private SuppressionGate $gate,
        private SuppressionRepositoryPort $suppressions,
    ) {}

    public function handle(string $postingUuid): ?Company
    {
        $posting = $this->postings->byUuid($postingUuid);

        if ($posting === null) {
            return null;
        }

        if ($posting->companyId !== null) {
            return $this->companies->byId($posting->companyId);
        }

        $domain = $this->payloadDomain($posting) ?? $this->searchDomain($posting);

        if ($domain === null) {
            return null;
        }

        $company = $this->findOrCreate($domain, $posting);

        if ($company !== null) {
            $this->postings->assignCompany($posting->id, $company->id);
        }

        return $company;
    }

    private function payloadDomain(JobPosting $posting): ?string
    {
        if ($posting->companyUrl === null || trim($posting->companyUrl) === '') {
            return null;
        }

        try {
            return CanonicalDomain::fromUrl(trim($posting->companyUrl))->value;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function searchDomain(JobPosting $posting): ?string
    {
        $probe = trim(($posting->companyName ?? '').' '.($posting->country ?? '').' official site');

        try {
            $results = $this->search->search(new SearchQuery(
                text: $probe,
                purpose: 'resolve',
                depth: 'basic',
                maxResults: 5,
            ));
        } catch (\Exception) {
            return null;
        }

        foreach ($results as $result) {
            try {
                return CanonicalDomain::fromUrl($result->url)->value;
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return null;
    }

    private function findOrCreate(string $domain, JobPosting $posting): ?Company
    {
        $existing = $this->companies->byDomain($domain) ?? $this->companies->byAlias($domain);

        if ($existing !== null) {
            return $existing;
        }

        $companyName = trim((string) $posting->companyName) !== '' ? trim((string) $posting->companyName) : 'Unknown';
        $candidates = $this->suppressions->matching($domain, null, $companyName);

        if ($this->gate->isSuppressed($domain, null, $companyName, $candidates)) {
            return null;
        }

        return $this->companies->register(
            canonicalDomain: $domain,
            name: mb_substr($companyName, 0, 255),
            origin: CompanyOrigin::JobPosting,
            originRef: mb_substr($posting->sourceUrl, 0, 255),
            country: $posting->country,
        );
    }
}
