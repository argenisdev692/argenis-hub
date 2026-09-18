<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/** Lever board endpoint (T-034): `additionalPlain` carries the full text. */
final readonly class LeverBoardSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'lever';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(5)->retry(2, 200)->get("https://api.lever.co/v0/postings/{$query}");
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];

        foreach (array_slice((array) $response->json(), 0, $limit) as $job) {
            $postings[] = [
                'url' => (string) ($job['hostedUrl'] ?? ''),
                'title' => (string) ($job['text'] ?? ''),
                'snippet' => null,
                'employer' => null,
                'location' => (string) ($job['categories']['location'] ?? ''),
                'posted_at' => isset($job['createdAt']) ? date('Y-m-d', (int) ($job['createdAt'] / 1000)) : null,
                'posted_at_source' => isset($job['createdAt']) ? 'lever' : 'unknown',
                'discovery_channel' => 'employer_ats',
                'full_text' => (string) ($job['additionalPlain'] ?? $job['descriptionPlain'] ?? ''),
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
