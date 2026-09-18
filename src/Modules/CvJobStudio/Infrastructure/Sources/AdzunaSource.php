<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/**
 * Adzuna API (T-040): app_id + key, ~1,000 calls/month free, 16 countries.
 * No credentials → no calls, never an error. Spend lands in the `search`
 * budget category (FR-33).
 */
final readonly class AdzunaSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'adzuna';
    }

    public function harvest(string $query, int $limit): array
    {
        $appId = (string) config('services.adzuna.app_id', '');
        $apiKey = (string) config('services.adzuna.api_key', '');

        if ($appId === '' || $apiKey === '') {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        try {
            $response = Http::timeout(5)->retry(1, 500)->get('https://api.adzuna.com/v1/api/jobs/gb/search/1', [
                'app_id' => $appId,
                'app_key' => $apiKey,
                'what' => $query,
                'results_per_page' => min($limit, 50),
                'content-type' => 'application/json',
            ]);
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];

        foreach (array_slice((array) $response->json('results', []), 0, $limit) as $job) {
            if (! is_array($job)) {
                continue;
            }

            $postings[] = [
                'url' => (string) ($job['redirect_url'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => mb_substr(strip_tags((string) ($job['description'] ?? '')), 0, 500),
                'employer' => (string) ($job['company']['display_name'] ?? ''),
                'location' => (string) ($job['location']['display_name'] ?? ''),
                'posted_at' => isset($job['created']) ? substr((string) $job['created'], 0, 10) : null,
                'posted_at_source' => isset($job['created']) ? 'adzuna' : 'unknown',
                'discovery_channel' => 'board',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
