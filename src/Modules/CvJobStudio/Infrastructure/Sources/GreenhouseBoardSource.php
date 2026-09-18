<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;

/**
 * Employer-sanctioned Greenhouse board endpoint (T-033, CHG-1): `?content=true`
 * returns the authoritative JD text, so extraction is skipped entirely for
 * these postings. 404 means "not a customer" (ATS-detection probe, T-009).
 */
final readonly class GreenhouseBoardSource implements PostingSourcePort
{
    public function __construct(private SpendGuardPort $spend) {}

    public function sourceName(): string
    {
        return 'greenhouse';
    }

    public function harvest(string $query, int $limit): array
    {
        // $query carries the company slug for company-scoped ATS boards.
        // A 404 (not a customer) arrives as a RequestException because the
        // call retries — either way there is no data, never an error.
        try {
            $response = Http::timeout(5)->retry(2, 200)->get(
                "https://boards-api.greenhouse.io/v1/boards/{$query}/jobs",
                ['content' => 'true'],
            );
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];

        foreach (array_slice((array) $response->json('jobs', []), 0, $limit) as $job) {
            $postings[] = [
                'url' => (string) ($job['absolute_url'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => null,
                'employer' => null,
                'location' => (string) (($job['location'] ?? [])['name'] ?? ''),
                'posted_at' => null,
                'posted_at_source' => 'unknown',
                'discovery_channel' => 'employer_ats',
                'full_text' => isset($job['content']) ? strip_tags((string) $job['content']) : '',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
