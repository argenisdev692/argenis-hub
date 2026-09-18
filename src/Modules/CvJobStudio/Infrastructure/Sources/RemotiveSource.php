<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/**
 * Remotive API (T-039): keyless; attribution required, 24h publish delay,
 * no resubmission to third-party sites (FR-35, T-049).
 */
final readonly class RemotiveSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'remotive';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(10)->retry(2, 200)->get('https://remotive.com/api/remote-jobs', [
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

        foreach (array_slice((array) $response->json('jobs', []), 0, $limit) as $job) {
            if (! is_array($job)) {
                continue;
            }

            $postings[] = [
                'url' => (string) ($job['url'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => mb_substr(strip_tags((string) ($job['description'] ?? '')), 0, 500),
                'employer' => (string) ($job['company_name'] ?? ''),
                'location' => (string) ($job['candidate_required_location'] ?? ''),
                'posted_at' => isset($job['publication_date']) ? substr((string) $job['publication_date'], 0, 10) : null,
                'posted_at_source' => isset($job['publication_date']) ? 'remotive' : 'unknown',
                'discovery_channel' => 'board',
                'full_text' => strip_tags((string) ($job['description'] ?? '')),
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
