<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/**
 * Arbeitnow EU endpoint (T-038): ATS-direct inventory (Greenhouse,
 * SmartRecruiters, Join.com, TeamTailor, Recruitee, Comeet), no key.
 */
final readonly class ArbeitnowSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'arbeitnow';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(5)->retry(2, 200)->get('https://arbeitnow.com/api/job-board-api');
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];
        $needle = mb_strtolower($query);

        foreach ((array) $response->json('data', []) as $job) {
            if (count($postings) >= $limit) {
                break;
            }

            $haystack = mb_strtolower((string) ($job['title'] ?? '').' '.(string) ($job['description'] ?? ''));

            if ($needle !== '' && ! str_contains($haystack, $needle)) {
                continue;
            }

            $postings[] = [
                'url' => (string) ($job['url'] ?? $job['job_url'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => null,
                'employer' => (string) ($job['company_name'] ?? ''),
                'location' => (string) ($job['location'] ?? ''),
                'posted_at' => isset($job['created_at']) ? substr((string) $job['created_at'], 0, 10) : null,
                'posted_at_source' => isset($job['created_at']) ? 'arbeitnow' : 'unknown',
                'discovery_channel' => 'board',
                'full_text' => (string) ($job['description'] ?? ''),
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
