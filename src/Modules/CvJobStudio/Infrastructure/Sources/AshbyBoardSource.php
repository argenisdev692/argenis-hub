<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/** Ashby board endpoint (T-035). Payloads can be large — streamed, not paged. */
final readonly class AshbyBoardSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'ashby';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(10)->retry(2, 200)->get("https://api.ashbyhq.com/posting-api/job-board/{$query}");
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];

        foreach (array_slice((array) $response->json('jobs', []), 0, $limit) as $job) {
            $postings[] = [
                'url' => (string) ($job['jobUrl'] ?? ''),
                'title' => (string) ($job['title'] ?? ''),
                'snippet' => null,
                'employer' => null,
                'location' => (string) ($job['locationName'] ?? ''),
                'posted_at' => null,
                'posted_at_source' => 'unknown',
                'discovery_channel' => 'employer_ats',
                'full_text' => (string) ($job['descriptionPlain'] ?? ''),
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
