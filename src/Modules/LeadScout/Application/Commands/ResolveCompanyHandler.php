<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\SearchPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Domain\ValueObjects\SearchQuery;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;

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
        private SearchPort $search,
        private SuppressionGate $gate,
    ) {}

    public function handle(string $postingUuid): ?ScoutCompanyEloquentModel
    {
        $posting = ScoutJobPostingEloquentModel::query()->with('company')->where('uuid', $postingUuid)->first();

        if ($posting === null || $posting->company !== null) {
            return $posting?->company;
        }

        $domain = $this->payloadDomain($posting);

        if ($domain === null) {
            $domain = $this->searchDomain($posting);
        }

        if ($domain === null) {
            return null;
        }

        $company = $this->findOrCreate($domain, $posting);

        if ($company !== null) {
            $posting->update(['company_id' => $company->id]);
        }

        return $company;
    }

    private function payloadDomain(ScoutJobPostingEloquentModel $posting): ?string
    {
        if ($posting->company_url === null || trim($posting->company_url) === '') {
            return null;
        }

        try {
            return CanonicalDomain::fromUrl(trim($posting->company_url))->value;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function searchDomain(ScoutJobPostingEloquentModel $posting): ?string
    {
        $probe = trim(($posting->company_name ?? '').' '.($posting->country ?? '').' official site');

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

    private function findOrCreate(
        string $domain,
        ScoutJobPostingEloquentModel $posting,
    ): ?ScoutCompanyEloquentModel {
        $existing = $this->companies->findByDomain($domain);

        if ($existing !== null) {
            return $existing;
        }

        $aliased = ScoutCompanyEloquentModel::query()
            ->whereJsonContains('aliases', $domain)
            ->first();

        if ($aliased !== null) {
            return $aliased;
        }

        $companyName = trim((string) $posting->company_name) !== '' ? trim((string) $posting->company_name) : 'Unknown';

        $candidates = $this->companies->suppressionsMatching($domain, null, $companyName)
            ->map(static fn ($row): array => [
                'canonical_domain' => $row->canonical_domain,
                'tax_id' => $row->tax_id,
                'name' => $row->name,
            ])
            ->all();

        if ($this->gate->isSuppressed($domain, null, $companyName, $candidates)) {
            return null;
        }

        return $this->companies->create([
            'canonical_domain' => $domain,
            'name' => mb_substr($companyName, 0, 255),
            'country' => $posting->country,
            'origin' => CompanyOrigin::JobPosting->value,
            'origin_ref' => mb_substr($posting->source_url, 0, 255),
        ]);
    }
}
