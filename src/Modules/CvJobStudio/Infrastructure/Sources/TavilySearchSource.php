<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;
use Modules\CvJobStudio\Domain\Services\NeverFetchHostPolicy;
use Shared\Infrastructure\Research\TavilyClientInterface;

/**
 * Gap-filling + unknown-employer discovery over the DIRECT Tavily API
 * (T-041, FR-9) — the model-context-protocol bridge is test tooling only and
 * never runs on a production path (T-046). Person names and contact details
 * inside a snippet are not retained (NFR-8): only URL, title and snippet are
 * stored, whatever date the snippet carries. Never fetches.
 */
final readonly class TavilySearchSource implements PostingSourcePort
{
    public function __construct(
        private TavilyClientInterface $tavily,
        private NeverFetchHostPolicy $access,
    ) {}

    public function sourceName(): string
    {
        return 'tavily';
    }

    public function harvest(string $query, int $limit): array
    {
        $results = $this->tavily->search([$query], 'month', 'basic', $this->linkOnlyHosts());

        $postings = [];

        foreach (array_slice($results, 0, $limit) as $result) {
            $postings[] = [
                'url' => $result['url'],
                'title' => $result['title'],
                'snippet' => $result['content'],
                'employer' => null,
                'location' => null,
                'posted_at' => null,
                'posted_at_source' => 'unknown',
                'discovery_channel' => 'search',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }

    /** @return list<string> */
    private function linkOnlyHosts(): array
    {
        return (array) config('cv-job-studio.never_fetch_hosts', []);
    }
}
