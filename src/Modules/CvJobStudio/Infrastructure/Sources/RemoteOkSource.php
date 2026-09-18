<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/** RemoteOK API (T-039): keyless; attribution + link-back required (FR-35). */
final readonly class RemoteOkSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'remoteok';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(10)->retry(2, 200)->get('https://remoteok.com/api');
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];
        $needle = mb_strtolower($query);

        foreach ((array) $response->json() as $job) {
            if (count($postings) >= $limit) {
                break;
            }

            if (! is_array($job) || ! isset($job['id'], $job['position'])) {
                continue;
            }

            $haystack = mb_strtolower((string) ($job['position'] ?? '').' '.strip_tags((string) ($job['description'] ?? '')));

            if ($needle !== '' && ! str_contains($haystack, $needle)) {
                continue;
            }

            $postings[] = [
                'url' => (string) ($job['url'] ?? "https://remoteok.com/remote-jobs/{$job['id']}"),
                'title' => (string) ($job['position'] ?? ''),
                'snippet' => mb_substr(strip_tags((string) ($job['description'] ?? '')), 0, 500),
                'employer' => (string) ($job['company'] ?? ''),
                'location' => (string) ($job['location'] ?? ''),
                'posted_at' => isset($job['date']) ? substr((string) $job['date'], 0, 10) : null,
                'posted_at_source' => isset($job['date']) ? 'remoteok' : 'unknown',
                'discovery_channel' => 'board',
                'full_text' => strip_tags((string) ($job['description'] ?? '')),
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
