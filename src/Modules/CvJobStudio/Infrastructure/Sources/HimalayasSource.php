<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/** Himalayas API (T-039): keyless; carries salary/seniority metadata. */
final readonly class HimalayasSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'himalayas';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(10)->retry(2, 200)->get('https://himalayas.app/api/jobs', [
                'search' => $query !== '' ? $query : null,
                'limit' => $limit,
            ]);
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];
        $jobs = $response->json('jobs', $response->json());

        foreach (array_slice(is_array($jobs) ? $jobs : [], 0, $limit) as $job) {
            if (! is_array($job)) {
                continue;
            }

            $postings[] = [
                'url' => (string) ($job['url'] ?? $job['application_url'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => mb_substr(strip_tags((string) ($job['description'] ?? $job['excerpt'] ?? '')), 0, 500),
                'employer' => (string) ($job['companyName'] ?? $job['company_name'] ?? ''),
                'location' => (string) ($job['location'] ?? ''),
                'posted_at' => isset($job['publishedAt']) ? substr((string) $job['publishedAt'], 0, 10) : null,
                'posted_at_source' => isset($job['publishedAt']) ? 'himalayas' : 'unknown',
                'discovery_channel' => 'board',
                'full_text' => strip_tags((string) ($job['description'] ?? '')),
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
