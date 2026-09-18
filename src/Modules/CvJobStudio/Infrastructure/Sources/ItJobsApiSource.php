<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/**
 * ITJobs.pt API (T-114, V-5): provider-issued read-only key in
 * `config/services.php` (requested by email at itjobs.pt/api), 1
 * request/second (`Crawl-delay: 1`), `usage_restriction =
 * personal_non_commercial`. No key → no calls, never an error.
 */
final readonly class ItJobsApiSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'itjobs';
    }

    public function harvest(string $query, int $limit): array
    {
        $apiKey = (string) config('services.itjobs.api_key', '');

        if ($apiKey === '') {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        try {
            $response = Http::withToken($apiKey)->timeout(5)->retry(1, 1000)->get(
                'https://api.itjobs.pt/api/search',
                ['q' => $query, 'limit' => $limit],
            );
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];

        foreach (array_slice((array) $response->json('results', []), 0, $limit) as $job) {
            $postings[] = [
                'url' => (string) ($job['url'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => (string) ($job['body'] ?? ''),
                'employer' => (string) ($job['company'] ?? ''),
                'location' => (string) ($job['location'] ?? ''),
                'posted_at' => isset($job['published_at']) ? substr((string) $job['published_at'], 0, 10) : null,
                'posted_at_source' => isset($job['published_at']) ? 'itjobs' : 'unknown',
                'discovery_channel' => 'board',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
