<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/** Jobicy API v2 (T-039): keyless remote-jobs feed. */
final readonly class JobicySource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'jobicy';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(10)->retry(2, 200)->get('https://jobicy.com/api/v2/remote-jobs');
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];
        $needle = mb_strtolower($query);

        foreach ((array) $response->json('jobs', []) as $job) {
            if (count($postings) >= $limit) {
                break;
            }

            if (! is_array($job)) {
                continue;
            }

            $haystack = mb_strtolower((string) ($job['jobTitle'] ?? '').' '.strip_tags((string) ($job['jobDescription'] ?? '')));

            if ($needle !== '' && ! str_contains($haystack, $needle)) {
                continue;
            }

            $postings[] = [
                'url' => (string) ($job['url'] ?? ''),
                'title' => (string) ($job['jobTitle'] ?? ''),
                'snippet' => mb_substr(strip_tags((string) ($job['jobExcerpt'] ?? '')), 0, 500),
                'employer' => (string) ($job['companyName'] ?? ''),
                'location' => (string) ($job['jobLocation'] ?? ''),
                'posted_at' => isset($job['pubDate']) ? date('Y-m-d', strtotime((string) $job['pubDate']) ?: time()) : null,
                'posted_at_source' => isset($job['pubDate']) ? 'jobicy' : 'unknown',
                'discovery_channel' => 'board',
                'full_text' => strip_tags((string) ($job['jobDescription'] ?? '')),
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
