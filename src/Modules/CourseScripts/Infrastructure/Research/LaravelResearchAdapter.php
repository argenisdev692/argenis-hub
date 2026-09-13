<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Research;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Domain\Ports\ResearchPort;
use Modules\CourseScripts\Domain\ValueObjects\ResearchBatch;
use Modules\CourseScripts\Domain\ValueObjects\ResearchFinding;
use Shared\Infrastructure\Research\FirecrawlClientInterface;
use Shared\Infrastructure\Research\TavilyClientInterface;

/**
 * {@see ResearchPort} over the shared Tavily and Firecrawl clients, both
 * breaker-wrapped and fail-soft. Counts one call per query actually sent and
 * one per page fetched (FR-13i).
 */
final readonly class LaravelResearchAdapter implements ResearchPort
{
    private const int TAVILY_MAX_QUERIES = 4;

    public function __construct(
        private TavilyClientInterface $tavily,
        private FirecrawlClientInterface $firecrawl,
        private Config $config,
    ) {}

    public function search(array $queries, ?string $timeRange = null): ResearchBatch
    {
        $queries = array_values(array_filter($queries, static fn (string $query): bool => trim($query) !== ''));

        if ($queries === [] || (string) $this->config->get('services.tavily.api_key') === '') {
            return ResearchBatch::empty();
        }

        $queries = array_slice($queries, 0, self::TAVILY_MAX_QUERIES);
        $maxChars = (int) $this->config->get('course-scripts.research.finding_content_max_chars', 4000);
        $findings = [];
        $seen = [];

        // One query per call so each finding keeps the query that found it.
        foreach ($queries as $query) {
            foreach ($this->tavily->search([$query], $timeRange) as $result) {
                if ($result['url'] === '' || isset($seen[$result['url']])) {
                    continue;
                }

                $seen[$result['url']] = true;
                $findings[] = new ResearchFinding(
                    provider: 'tavily',
                    query: mb_substr($query, 0, 500),
                    url: mb_substr($result['url'], 0, 2048),
                    title: mb_substr($result['title'], 0, 500),
                    content: mb_substr($result['content'], 0, $maxChars),
                    score: $result['score'],
                );
            }
        }

        return new ResearchBatch($findings, count($queries));
    }

    public function fetchFullPage(ResearchFinding $finding): ?ResearchFinding
    {
        if (! (bool) $this->config->get('course-scripts.research.firecrawl_enabled', true)) {
            return null;
        }

        $markdown = $this->firecrawl->scrape($finding->url);

        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        return $finding->withContent(
            mb_substr($markdown, 0, (int) $this->config->get('course-scripts.research.finding_content_max_chars', 4000)),
            true,
        );
    }
}
