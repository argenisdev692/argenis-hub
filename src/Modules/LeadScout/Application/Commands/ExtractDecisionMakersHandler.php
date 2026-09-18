<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Domain\ValueObjects\RoleTaxonomy;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;

/**
 * Decisor candidates from stored pages (spec US-11, T048): in-memory for
 * PII scrubbing upstream, persisted ONLY when the current tier is A/B
 * (FR-27). Downgrades to C/Discarded anonymize stored decisors. Without
 * an identifiable decisor the lead is NOT discarded (`has_decision_maker`
 * stays false, form/offer channels carry the contact).
 */
final readonly class ExtractDecisionMakersHandler
{
    public function __construct(private DecisionMakerExtractor $extractor) {}

    /**
     * @return list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>
     */
    public function candidates(string $companyUuid): array
    {
        $company = ScoutCompanyEloquentModel::query()->where('uuid', $companyUuid)->first()
            ?? throw new CompanyNotFoundException($companyUuid);

        return $this->extractFor($company)['candidates'];
    }

    /**
     * @return array{persisted: int, anonymized: int}
     */
    public function persistIfTierAB(string $companyUuid): array
    {
        $company = ScoutCompanyEloquentModel::query()->where('uuid', $companyUuid)->first()
            ?? throw new CompanyNotFoundException($companyUuid);

        $tier = $company->scoreResults()->where('is_current', true)->value('tier');

        if ($tier !== Tier::A->value && $tier !== Tier::B->value) {
            return ['persisted' => 0, 'anonymized' => $this->anonymize($company)];
        }

        ['candidates' => $candidates, 'teamSize' => $teamSize] = $this->extractFor($company);

        $company->update([
            'has_decision_maker' => $candidates !== [],
            'team_size_observed' => $teamSize ?? $company->team_size_observed,
        ]);

        if ($candidates === []) {
            return ['persisted' => 0, 'anonymized' => 0];
        }

        $primary = $this->pickPrimary($candidates, $teamSize);
        $persisted = 0;

        foreach ($candidates as $candidate) {
            $exists = ScoutContactEloquentModel::query()
                ->where('company_id', $company->id)
                ->where('full_name', $candidate['name'])
                ->whereNull('anonymized_at')
                ->exists();

            if ($exists) {
                continue;
            }

            ScoutContactEloquentModel::query()->create([
                'company_id' => $company->id,
                'full_name' => $candidate['name'],
                'role_title' => $candidate['title'],
                'role_category' => $candidate['category'],
                'is_primary' => $primary !== null && $candidate['name'] === $primary['name'],
                'published_email' => $candidate['email'],
                'email_kind' => $candidate['email_kind'],
                'public_profile_url' => $candidate['profile_url'],
                'source' => 'website',
                'evidence_url' => $candidate['evidence_url'],
                'evidence_excerpt' => $candidate['excerpt'],
                'evidence_captured_at' => $candidate['captured_at'],
                'contact_deadline_at' => CarbonImmutable::now()->addDays(30),
                'last_verified_at' => $candidate['captured_at'],
            ]);
            $persisted++;
        }

        return ['persisted' => $persisted, 'anonymized' => 0];
    }

    /**
     * @return array{candidates: list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>, teamSize: ?int}
     */
    private function extractFor(ScoutCompanyEloquentModel $company): array
    {
        $pages = $company->fetchedPages()
            ->whereNotNull('content_markdown')
            ->orderBy('id')
            ->get(['url', 'content_markdown'])
            ->map(static fn ($page): array => ['url' => $page->url, 'markdown' => (string) $page->content_markdown])
            ->all();

        $opposed = ScoutContactObjectionEloquentModel::query()->pluck('person_hash')->all();

        return $this->extractor->extract($pages, $company->canonical_domain, [], $opposed);
    }

    private function anonymize(ScoutCompanyEloquentModel $company): int
    {
        return ScoutContactEloquentModel::query()
            ->where('company_id', $company->id)
            ->whereNull('anonymized_at')
            ->update([
                'full_name' => null,
                'published_email' => null,
                'public_profile_url' => null,
                'evidence_excerpt' => null,
                'anonymized_at' => now(),
            ]);
    }

    /**
     * @param  list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>  $candidates
     * @return array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}|null
     */
    private function pickPrimary(array $candidates, ?int $teamSize): ?array
    {
        foreach (RoleTaxonomy::preferredOrder($teamSize) as $category) {
            foreach ($candidates as $candidate) {
                if ($candidate['category'] === $category->value) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
