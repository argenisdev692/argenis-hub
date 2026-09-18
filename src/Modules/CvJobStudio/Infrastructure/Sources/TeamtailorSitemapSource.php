<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;
use Modules\CvJobStudio\Domain\Services\OutboundUrlGuard;
use Modules\CvJobStudio\Domain\Services\RobotsTxtPolicy;

/**
 * Teamtailor company sitemap walker (T-005/T-037 resolution): the official
 * API needs a per-tenant X-Api-Key with no aggregator path, so unauthenticated
 * discovery walks the public `{slug}.teamtailor.com/sitemap.xml` instead —
 * URLs only, full text comes later through the permitted ladder. Registered
 * with `requires_api_key = false, company_scoped = true`.
 */
final readonly class TeamtailorSitemapSource implements PostingSourcePort
{
    public function __construct(
        private OutboundUrlGuard $guard,
        private RobotsTxtPolicy $robots,
    ) {}

    public function sourceName(): string
    {
        return 'teamtailor';
    }

    public function harvest(string $query, int $limit): array
    {
        $sitemap = "https://{$query}.teamtailor.com/sitemap.xml";

        if (! $this->guard->allowed($sitemap) || ! $this->robots->mayFetch($sitemap)) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        try {
            $response = Http::timeout(10)->retry(1, 500)->get($sitemap);
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $xml = @simplexml_load_string($response->body());

        if ($xml === false) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];

        foreach ($xml->url ?? [] as $entry) {
            if (count($postings) >= $limit) {
                break;
            }

            $url = (string) ($entry->loc ?? '');

            if (! str_contains($url, '/jobs/')) {
                continue;
            }

            $postings[] = [
                'url' => $url,
                'title' => (string) ($entry->title ?? basename(rtrim($url, '/'))),
                'snippet' => null,
                'employer' => null,
                'location' => null,
                'posted_at' => isset($entry->lastmod) ? substr((string) $entry->lastmod, 0, 10) : null,
                'posted_at_source' => isset($entry->lastmod) ? 'teamtailor' : 'unknown',
                'discovery_channel' => 'employer_site',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
