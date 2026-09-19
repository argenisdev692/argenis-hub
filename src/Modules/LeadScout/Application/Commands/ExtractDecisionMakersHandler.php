<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Entities\FetchedPage;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\FetchedPageRepositoryPort;
use Modules\LeadScout\Domain\Ports\ScoreResultRepositoryPort;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Domain\ValueObjects\RoleTaxonomy;

/**
 * Decisor candidates from stored pages (spec US-11, T048): in-memory for
 * PII scrubbing upstream, persisted ONLY when the current tier is A/B
 * (FR-27). Downgrades to C/Discarded anonymize stored decisors. Without
 * an identifiable decisor the lead is NOT discarded (`has_decision_maker`
 * stays false, form/offer channels carry the contact).
 */
final readonly class ExtractDecisionMakersHandler
{
    private const int CONTACT_DEADLINE_DAYS = 30;

    public function __construct(
        private DecisionMakerExtractor $extractor,
        private CompanyRepositoryPort $companies,
        private ContactRepositoryPort $contacts,
        private FetchedPageRepositoryPort $pages,
        private ScoreResultRepositoryPort $scores,
    ) {}

    /**
     * @return list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>
     */
    public function candidates(string $companyUuid): array
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);

        return $this->extractFor($company)['candidates'];
    }

    /**
     * @return array{persisted: int, anonymized: int}
     */
    public function persistIfTierAB(string $companyUuid): array
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);
        $tier = $this->scores->currentTier($company->id);

        if ($tier !== Tier::A && $tier !== Tier::B) {
            return ['persisted' => 0, 'anonymized' => $this->contacts->anonymizeCompany($company->id, CarbonImmutable::now())];
        }

        ['candidates' => $candidates, 'teamSize' => $teamSize] = $this->extractFor($company);

        $this->companies->recordDecisionMakers($company, $candidates !== [], $teamSize);

        if ($candidates === []) {
            return ['persisted' => 0, 'anonymized' => 0];
        }

        $primary = self::pickPrimary($candidates, $teamSize);
        $deadline = CarbonImmutable::now()->addDays(self::CONTACT_DEADLINE_DAYS);
        $persisted = 0;

        foreach ($candidates as $candidate) {
            if ($this->contacts->hasLiveContactNamed($company->id, $candidate['name'])) {
                continue;
            }

            $this->contacts->createExtracted(
                $company->id,
                $candidate,
                $primary !== null && $candidate['name'] === $primary['name'],
                $deadline,
            );
            $persisted++;
        }

        return ['persisted' => $persisted, 'anonymized' => 0];
    }

    /**
     * @return array{candidates: list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>, teamSize: ?int}
     */
    private function extractFor(Company $company): array
    {
        $pages = array_map(
            static fn (FetchedPage $page): array => ['url' => $page->url, 'markdown' => (string) $page->contentMarkdown],
            $this->pages->withContent($company->id),
        );

        return $this->extractor->extract($pages, $company->canonicalDomain, [], $this->contacts->objectionHashes());
    }

    /**
     * @param  list<array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}>  $candidates
     * @return array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}|null
     */
    private static function pickPrimary(array $candidates, ?int $teamSize): ?array
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
