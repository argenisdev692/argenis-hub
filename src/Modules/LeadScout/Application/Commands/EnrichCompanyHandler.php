<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Exceptions\CompanyNotFoundException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Services\PublicCompanyDataExtractor;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Infrastructure\Fetching\FetchLadder;
use Modules\LeadScout\Infrastructure\Fetching\FormSummaryExtractor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchedPageEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;
use Modules\LeadScout\Infrastructure\Queue\ExtractSignalsJob;
use Modules\LeadScout\Infrastructure\Queue\ScoreCompanyJob;

/**
 * Progressive enrichment (spec US-7 CA-3/CA-8, T045): sitemap/home →
 * ≤ 4 keyword pages (multilingual) via the cost ladder → stored pages
 * → public company data (allowlist) → channels → score. Stops with
 * sufficient evidence; `needs_research` earns exactly one extra round
 * over unvisited cases/blog pages before re-scoring (no loops).
 *
 * A natural person (FR-39) stores no identification: a `solo_freelancer`
 * fact is recorded and scoring discards it with the reason.
 *
 * @return array{status: string, pages: int}
 */
final readonly class EnrichCompanyHandler
{
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

    public function __construct(
        private FetchLadder $ladder,
        private PublicCompanyDataExtractor $publicData,
        private DetectContactChannelsHandler $channels,
        private CompanyRepositoryPort $companies,
        private SuppressionGate $gate,
    ) {}

    public function handle(string $companyUuid): array
    {
        $company = ScoutCompanyEloquentModel::query()->where('uuid', $companyUuid)->first()
            ?? throw new CompanyNotFoundException($companyUuid);

        // Suppression wins over enrichment too (spec FR-43, T084).
        $candidates = $this->companies->suppressionsMatching($company->canonical_domain, $company->tax_id, $company->name)
            ->map(static fn ($row): array => [
                'canonical_domain' => $row->canonical_domain,
                'tax_id' => $row->tax_id,
                'name' => $row->name,
            ])
            ->all();

        if ($this->gate->isSuppressed($company->canonical_domain, $company->tax_id, $company->name, $candidates)) {
            return ['status' => 'suppressed', 'pages' => 0];
        }

        $extraRound = $company->needs_research;

        $sitemap = $this->fetchSitemap($company);
        $home = $this->ladder->fetch($company, 'https://'.$company->canonical_domain.'/');

        if ($home->succeeded()) {
            $this->storePage($company, 'https://'.$company->canonical_domain.'/', 'home', $home->markdown, $home->html);
        }

        $targets = $this->selectTargets($company, ($home->html ?? '')."\n".($home->markdown ?? ''), $sitemap['urls'], $extraRound);

        foreach ($targets as $target) {
            $result = $this->ladder->fetch($company, $target['url']);

            if ($result->succeeded()) {
                $this->storePage($company, $target['url'], $target['type'], $result->markdown, $result->html);
            }

            if ($this->hasEnoughEvidence($company)) {
                break;
            }
        }

        $pages = (int) $company->fetchedPages()->whereNotNull('content_markdown')->whereNotNull('page_type')->count();

        if ($pages === 0) {
            return ['status' => 'no_pages', 'pages' => 0];
        }

        if ($this->handleNaturalPerson($company)) {
            ScoreCompanyJob::dispatch($company->uuid, null, true);

            return ['status' => 'solo_freelancer', 'pages' => $pages];
        }

        $this->persistSitemapVitality($company, $sitemap['urls']);
        $this->channels->handle($company->uuid);

        Log::info('lead-scout.enrich_finished', [
            'company' => $company->uuid,
            'pages' => $pages,
            'extra_round' => $extraRound,
        ]);

        ExtractSignalsJob::dispatch($company->uuid, $extraRound);

        return ['status' => 'enriched', 'pages' => $pages];
    }

    /**
     * Sitemap freshness as a rule signal here: the raw XML is not kept as
     * evidence, so later stages could not derive it (T031 vitality).
     *
     * @param  array<string, ?string>  $sitemapUrls
     */
    private function persistSitemapVitality(ScoutCompanyEloquentModel $company, array $sitemapUrls): void
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
            $this->firstSignal($company, 'sitemap_fresh', "Sitemap {$latest->toDateString()}");
        } elseif ($months > 24) {
            $this->firstSignal($company, 'old_sitemap', "Sitemap {$latest->toDateString()}");
        }
    }

    private function firstSignal(ScoutCompanyEloquentModel $company, string $key, string $value): void
    {
        ScoutSignalEloquentModel::query()->firstOrCreate(
            ['company_id' => $company->id, 'signal_key' => $key],
            [
                'dimension' => 'vitality',
                'value_text' => $value,
                'nature' => 'fact',
                'confidence' => 75,
                'captured_at' => now(),
                'extraction_method' => 'rule',
            ],
        );
    }

    /**
     * @return array{urls: array<string, ?string>}
     */
    private function fetchSitemap(ScoutCompanyEloquentModel $company): array
    {
        $sitemapUrl = 'https://'.$company->canonical_domain.'/sitemap.xml';
        $result = $this->ladder->fetch($company, $sitemapUrl);

        // Sitemaps are XML: the markdown step strips every tag, so the raw
        // html field carries the <loc> payload here.
        if ($result->status !== FetchStatus::Ok || (($result->html ?? $result->markdown) === null)) {
            return ['urls' => []];
        }

        // Stored untyped: evidence for sitemap-lastmod vitality, excluded
        // from the keyword-page evidence count below.
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

        return ['urls' => $urls];
    }

    /**
     * @param  array<string, ?string>  $sitemapUrls
     * @return list<array{url: string, type: string}>
     */
    private function selectTargets(
        ScoutCompanyEloquentModel $company,
        string $homeContent,
        array $sitemapUrls,
        bool $extraRound,
    ): array {
        $links = [];
        $text = $homeContent;

        // Raw html first (href attributes), markdown links second.
        if (preg_match_all('/href\s*=\s*["\'](https?:\/\/[^"\'\s>]+)/i', $text, $hrefs) > 0) {
            foreach ($hrefs[1] as $link) {
                $links[] = $link;
            }
        }

        if (preg_match_all('/\((https?:\/\/[^\s)]+)\)/', $text, $found) > 0) {
            foreach ($found[1] as $link) {
                $links[] = $link;
            }
        }

        foreach (array_keys($sitemapUrls) as $loc) {
            $links[] = $loc;
        }

        $visited = $company->fetchedPages()->pluck('url')->all();
        $targets = [];
        $priority = $extraRound ? ['cases', 'blog', 'services', 'about'] : ['services', 'about', 'team', 'jobs', 'contact', 'cases'];

        foreach ($priority as $type) {
            if (count($targets) >= 4) {
                break;
            }

            foreach (array_unique($links) as $link) {
                if (count($targets) >= 4) {
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

    private function isSameSite(ScoutCompanyEloquentModel $company, string $link): bool
    {
        try {
            return CanonicalDomain::fromUrl($link)->value === $company->canonical_domain;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function hasEnoughEvidence(ScoutCompanyEloquentModel $company): bool
    {
        return $company->fetchedPages()->whereNotNull('content_markdown')->whereNotNull('page_type')->count() >= 4;
    }

    private function storePage(
        ScoutCompanyEloquentModel $company,
        string $url,
        ?string $type,
        ?string $markdown,
        ?string $html,
    ): void {
        $markdown = $markdown !== null && trim($markdown) !== '' ? $markdown : null;
        $summary = FormSummaryExtractor::summarize($html, $url);

        ScoutFetchedPageEloquentModel::query()->updateOrCreate(
            ['company_id' => $company->id, 'url' => mb_substr($url, 0, 2048)],
            [
                'page_type' => $type,
                'content_markdown' => $markdown,
                'content_hash' => $markdown === null ? null : hash('sha256', $markdown),
                'forms_summary' => $summary,
                'fetched_at' => now(),
            ],
        );
    }

    /**
     * Natural person (spec FR-39): persist the verdict as a signal and let
     * scoring discard it — identification fields are never stored.
     */
    private function handleNaturalPerson(ScoutCompanyEloquentModel $company): bool
    {
        $pages = $company->fetchedPages()
            ->whereNotNull('content_markdown')
            ->orderBy('id')
            ->get(['url', 'page_type', 'content_markdown'])
            ->map(static fn ($page): array => [
                'url' => $page->url,
                'page_type' => $page->page_type?->value,
                'markdown' => (string) $page->content_markdown,
            ])
            ->all();

        $extracted = $this->publicData->extract($pages);

        if (! $extracted['is_natural_person']) {
            $this->storePublicData($company, $extracted);

            return false;
        }

        ScoutSignalEloquentModel::query()->firstOrCreate(
            ['company_id' => $company->id, 'signal_key' => 'solo_freelancer'],
            [
                'dimension' => 'vitality',
                'nature' => 'fact',
                'confidence' => 85,
                'evidence_url' => array_key_first($extracted['evidence']) !== null
                    ? $extracted['evidence'][array_key_first($extracted['evidence'])]['url']
                    : null,
                'evidence_excerpt' => 'Natural person (sole trader).',
                'captured_at' => now(),
                'extraction_method' => 'rule',
            ],
        );

        return true;
    }

    /**
     * @param  array{data: array<string, mixed>, evidence: array<string, array{url: string, excerpt: string, captured_at: string}>, is_natural_person: bool}  $extracted
     */
    private function storePublicData(ScoutCompanyEloquentModel $company, array $extracted): void
    {
        $allowed = [
            'legal_name', 'legal_form', 'tax_id', 'registry_info', 'city', 'founded_year',
            'services', 'sectors', 'site_languages', 'client_companies', 'public_urls',
        ];

        $attributes = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $extracted['data'])) {
                $attributes[$field] = $extracted['data'][$field];
            }
        }

        $attributes['public_data_evidence'] = $extracted['evidence'];

        if ($attributes !== ['public_data_evidence' => $extracted['evidence']]) {
            $company->update($attributes);
        } else {
            $company->update(['public_data_evidence' => $extracted['evidence']]);
        }
    }
}
