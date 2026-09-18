<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/** Workable account board (T-036): keyless public widget endpoint. */
final readonly class WorkableBoardSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'workable';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(5)->retry(2, 200)->get("https://apply.workable.com/api/accounts/{$query}/jobs");
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
                'url' => (string) ($job['url'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => isset($job['description']) ? mb_substr(strip_tags((string) $job['description']), 0, 500) : null,
                'employer' => null,
                'location' => (string) ($job['location'] ?? ''),
                'posted_at' => isset($job['published_on']) ? substr((string) $job['published_on'], 0, 10) : null,
                'posted_at_source' => isset($job['published_on']) ? 'workable' : 'unknown',
                'discovery_channel' => 'employer_ats',
                'full_text' => isset($job['description']) ? strip_tags((string) $job['description']) : '',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
