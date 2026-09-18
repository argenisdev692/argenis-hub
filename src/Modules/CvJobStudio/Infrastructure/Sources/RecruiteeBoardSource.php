<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;

/** Recruitee company board (T-036): keyless public offers endpoint. */
final readonly class RecruiteeBoardSource implements PostingSourcePort
{
    public function sourceName(): string
    {
        return 'recruitee';
    }

    public function harvest(string $query, int $limit): array
    {
        try {
            $response = Http::timeout(5)->retry(2, 200)->get("https://{$query}.recruitee.com/api/offers");
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];
        $offers = $response->json('offers', $response->json());

        foreach (array_slice(is_array($offers) ? $offers : [], 0, $limit) as $offer) {
            if (! is_array($offer)) {
                continue;
            }

            $postings[] = [
                'url' => (string) ($offer['careers_url'] ?? $offer['url'] ?? ''),
                'title' => (string) ($offer['title'] ?? ''),
                'snippet' => isset($offer['description']) ? mb_substr(strip_tags((string) $offer['description']), 0, 500) : null,
                'employer' => null,
                'location' => (string) ($offer['location'] ?? $offer['city'] ?? ''),
                'posted_at' => isset($offer['published_at']) ? substr((string) $offer['published_at'], 0, 10) : null,
                'posted_at_source' => isset($offer['published_at']) ? 'recruitee' : 'unknown',
                'discovery_channel' => 'employer_ats',
                'full_text' => isset($offer['description']) ? strip_tags((string) $offer['description']) : '',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
