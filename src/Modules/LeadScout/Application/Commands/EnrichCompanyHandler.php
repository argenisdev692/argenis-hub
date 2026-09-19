<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Entities\Company;
use Modules\LeadScout\Domain\Entities\FetchedPage;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Enums\PageType;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Ports\CompanyPageFetcherPort;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\FetchedPageRepositoryPort;
use Modules\LeadScout\Domain\Ports\PipelineLoggerPort;
use Modules\LeadScout\Domain\Ports\PipelineQueuePort;
use Modules\LeadScout\Domain\Ports\SignalRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Services\PublicCompanyDataExtractor;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Domain\ValueObjects\NewSignal;

/**
 * Progressive enrichment (spec US-7 CA-3/CA-8, T045): sitemap/home →
 * ≤ 4 keyword pages (multilingual) via the cost ladder → stored pages
 * → public company data (allowlist) → channels → score. Stops with
 * sufficient evidence; `needs_research` earns exactly one extra round
 * over unvisited cases/blog pages before re-scoring (no loops).
 *
 * A natural person (FR-39) stores no identification: a `solo_freelancer`
 * fact is recorded and scoring discards it with the reason.
 */
final readonly class EnrichCompanyHandler
{
    private const int MAX_TARGETS = 4;

    /**
     * @var array<string, list<string>>
     */
    private const array PAGE_KEYWORDS = [
        'services' => ['servicos', 'servicios', 'services'],
        'about' => ['sobre', 'nosotros', 'about', 'quem-somos', 'quienes-somos'],
        'team' => ['equipa', 'equipo', 'team'],
        'jobs' => ['carreiras', 'empleo', 'careers', 'jobs', 'trabaja', 'join'],
        'contact' => ['contacto', 'contactos', 'contact'],
        'cases' => ['casos', 'clientes', 'cases', 'clients', 'portfolio', 'proyectos'],
        'blog' => ['blog', 'noticias', 'news', 'novidades'],
        'partners' => ['partners', 'parceiros', 'socios', 'colabora'],
        'legal' => ['aviso-legal', 'termos', 'privacidad', 'privacidade', 'legal'],
    ];

    /** Public-data fields the allowlist lets us store (spec FR-38). */
    private const array PUBLIC_FIELDS = [
        'legal_name', 'legal_form', 'tax_id', 'registry_info', 'city', 'founded_year',
        'services', 'sectors', 'site_languages', 'client_companies', 'public_urls',
    ];

    public function __construct(
        private CompanyPageFetcherPort $fetcher,
        private PublicCompanyDataExtractor $publicData,
        private DetectContactChannelsHandler $channels,
        private CompanyRepositoryPort $companies,
        private FetchedPageRepositoryPort $pages,
        private SignalRepositoryPort $signals,
        private SuppressionGate $gate,
        private SuppressionRepositoryPort $suppressions,
        private PipelineQueuePort $queue,
        private PipelineLoggerPort $log,
    ) {}

    /**
     * @return array{status: string, pages: int}
     */
    public function handle(string $companyUuid): array
    {
        $company = $this->companies->byUuid($companyUuid) ?? throw new CompanyNotFoundException($companyUuid);

        // Suppression wins over enrichment too (spec FR-43, T084).
        $candidates = $this->suppressions->matching($company->canonicalDomain, $company->taxId, $company->name);

        if ($this->gate->isSuppressed($company->canonicalDomain, $company->taxId, $company->name, $candidates)) {
            return ['status' => 'suppressed', 'pages' => 0];
        }

        $extraRound = $company->needsResearch;
        $homeUrl = 'https://'.$company->canonicalDomain.'/';

        $sitemap = $this->fetchSitemap($company);
        $home = $this->fetcher->fetch($company->id, $homeUrl);

        if ($home->succeeded()) {
            $this->storePage($company, $homeUrl, PageType::Home, $home->markdown, $home->html);
        }

        $targets = $this->selectTargets($company, ($home->html ?? '')."\n".($home->markdown ?? ''), $sitemap, $extraRound);

        foreach ($targets as $target) {
            $result = $this->fetcher->fetch($company->id, $target['url']);

            if ($result->succeeded()) {
                $this->storePage($company, $target['url'], PageType::from($target['type']), $result->markdown, $result->html);
            }

            if ($this->pages->countEvidencePages($company->id) >= self::MAX_TARGETS) {
                break;
            }
        }

        $pages = $this->pages->countEvidencePages($company->id);

        if ($pages === 0) {
            return ['status' => 'no_pages', 'pages' => 0];
        }

        if ($this->handleNaturalPerson($company)) {
            $this->queue->scoreCompany($company->uuid, null, true);

            return ['status' => 'solo_freelancer', 'pages' => $pages];
        }

        $this->persistSitemapVitality($company, $sitemap);
        $this->channels->handle($company->uuid);

        $this->log->pipeline('enrich_finished', [
            'company' => $company->uuid,
            'pages' => $pages,
            'extra_round' => $extraRound,
        ]);

        $this->queue->extractSignals($company->uuid, $extraRound);

        return ['status' => 'enriched', 'pages' => $pages];
    }

    /**
     * Sitemap freshness as a rule signal here: the raw XML is not kept as
     * evidence, so later stages could not derive it (T031 vitality).
     *
     * @param  array<string, ?string>  $sitemapUrls
     */
    private function persistSitemapVitality(Company $company, array $sitemapUrls): void
    {
        $latest = null;

        foreach ($sitemapUrls as $lastmod) {
            if ($lastmod === null) {
                continue;
            }

            try {
                $date = CarbonImmutable::parse($lastmod);

                if ($latest === null || $date->gt($latest)) {
                    $latest = $date;
                }
            } catch (\Exception) {
            }
        }

        if ($latest === null) {
            return;
        }

        // Ignored when every URL shares one autogenerated lastmod (CMS artifact).
        if (count(array_unique(array_filter($sitemapUrls))) === 1 && count($sitemapUrls) > 3) {
            return;
        }

        $months = $latest->diffInMonths(CarbonImmutable::now());

        if ($months <= 6) {
            $this->vitalitySignal($company, 'sitemap_fresh', "Sitemap {$latest->toDateString()}");
        } elseif ($months > 24) {
            $this->vitalitySignal($company, 'old_sitemap', "Sitemap {$latest->toDateString()}");
        }
    }

    private function vitalitySignal(Company $company, string $key, string $value): void
    {
        $this->signals->addIfAbsent($company->id, new NewSignal(
            dimension: SignalDimension::Vitality,
            signalKey: $key,
            nature: SignalNature::Fact,
            confidence: 75,
            extractionMethod: ExtractionMethod::Rule,
            capturedAt: CarbonImmutable::now(),
            valueText: $value,
        ));
    }

    /**
     * @return array<string, ?string> sitemap location → lastmod
     */
    private function fetchSitemap(Company $company): array
    {
        $sitemapUrl = 'https://'.$company->canonicalDomain.'/sitemap.xml';
        $result = $this->fetcher->fetch($company->id, $sitemapUrl);

        // Sitemaps are XML: the markdown step strips every tag, so the raw
        // html field carries the <loc> payload here.
        if ($result->status !== FetchStatus::Ok || (($result->html ?? $result->markdown) === null)) {
            return [];
        }

        // Stored untyped: evidence for sitemap-lastmod vitality, excluded
        // from the keyword-page evidence count.
        $this->storePage($company, $sitemapUrl, null, $result->markdown, null);

        $urls = [];
        $xml = (string) ($result->html ?? $result->markdown);

        if (preg_match_all('/<loc>([^<]+)<\/loc>/i', $xml, $locs) > 0) {
            foreach ($locs[1] as $index => $loc) {
                $lastmod = null;

                if (preg_match_all('/<lastmod>([^<]+)<\/lastmod>/i', $xml, $mods) > 0 && isset($mods[1][$index])) {
                    $lastmod = trim($mods[1][$index]);
                }

                $urls[trim($loc)] = $lastmod;
            }
        }

        return $urls;
    }

    /**
     * @param  array<string, ?string>  $sitemapUrls
     * @return list<array{url: string, type: string}>
     */
    private function selectTargets(Company $company, string $homeContent, array $sitemapUrls, bool $extraRound): array
    {
        $links = [];

        // Raw html first (href attributes), markdown links second.
        if (preg_match_all('/href\s*=\s*["\'](https?:\/\/[^"\'\s>]+)/i', $homeContent, $hrefs) > 0) {
            foreach ($hrefs[1] as $link) {
                $links[] = $link;
            }
        }

        if (preg_match_all('/\((https?:\/\/[^\s)]+)\)/', $homeContent, $found) > 0) {
            foreach ($found[1] as $link) {
                $links[] = $link;
            }
        }

        foreach (array_keys($sitemapUrls) as $loc) {
            $links[] = $loc;
        }

        $visited = $this->pages->urlsFor($company->id);
        $targets = [];
        $priority = $extraRound ? ['cases', 'blog', 'services', 'about'] : ['services', 'about', 'team', 'jobs', 'contact', 'cases'];

        foreach ($priority as $type) {
            if (count($targets) >= self::MAX_TARGETS) {
                break;
            }

            foreach (array_unique($links) as $link) {
                if (count($targets) >= self::MAX_TARGETS) {
                    break;
                }

                if (in_array($link, $visited, true) || ! $this->isSameSite($company, $link)) {
                    continue;
                }

                foreach (self::PAGE_KEYWORDS[$type] as $keyword) {
                    if (str_contains(mb_strtolower($link), $keyword)) {
                        $targets[] = ['url' => $link, 'type' => $type];
                        $visited[] = $link;

                        break;
                    }
                }
            }
        }

        return $targets;
    }

    private function isSameSite(Company $company, string $link): bool
    {
        try {
            return CanonicalDomain::fromUrl($link)->value === $company->canonicalDomain;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function storePage(Company $company, string $url, ?PageType $type, ?string $markdown, ?string $html): void
    {
        $this->pages->store($company->id, $url, $type, $markdown, $html, CarbonImmutable::now());
    }

    /**
     * Natural person (spec FR-39): persist the verdict as a signal and let
     * scoring discard it — identification fields are never stored.
     */
    private function handleNaturalPerson(Company $company): bool
    {
        $pages = array_map(static fn (FetchedPage $page): array => [
            'url' => $page->url,
            'page_type' => $page->pageType?->value,
            'markdown' => (string) $page->contentMarkdown,
        ], $this->pages->withContent($company->id));

        $extracted = $this->publicData->extract($pages);

        if (! $extracted['is_natural_person']) {
            $this->companies->recordPublicData(
                $company,
                array_intersect_key($extracted['data'], array_flip(self::PUBLIC_FIELDS)),
                $extracted['evidence'],
            );

            return false;
        }

        $firstEvidence = array_first($extracted['evidence']);

        $this->signals->addIfAbsent($company->id, new NewSignal(
            dimension: SignalDimension::Vitality,
            signalKey: 'solo_freelancer',
            nature: SignalNature::Fact,
            confidence: 85,
            extractionMethod: ExtractionMethod::Rule,
            capturedAt: CarbonImmutable::now(),
            evidenceUrl: $firstEvidence['url'] ?? null,
            evidenceExcerpt: 'Natural person (sole trader).',
        ));

        return true;
    }
}
