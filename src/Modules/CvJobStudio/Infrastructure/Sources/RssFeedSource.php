<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Sources;

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\PostingSourcePort;
use Modules\CvJobStudio\Domain\Services\OutboundUrlGuard;
use Modules\CvJobStudio\Domain\Services\RobotsTxtPolicy;

/**
 * Generic RSS/Atom harvest (T-038, T-113 pattern): WeWorkRemotely and
 * Net-Empregos (ISO-8859-1 decoded, `api_feed`, personal non-commercial,
 * modest once-per-run polling). Guarded + robots-honoured like every fetch.
 */
final readonly class RssFeedSource implements PostingSourcePort
{
    public function __construct(
        private OutboundUrlGuard $guard,
        private RobotsTxtPolicy $robots,
    ) {}

    public function sourceName(): string
    {
        return 'rss';
    }

    public function harvest(string $query, int $limit): array
    {
        // $query carries the feed URL for feed-kind sources.
        if (! $this->guard->allowed($query) || ! $this->robots->mayFetch($query)) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        try {
            $response = Http::timeout(10)->retry(1, 500)->get($query);
        } catch (\Throwable) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        if ($response->failed()) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $xml = mb_convert_encoding($response->body(), 'UTF-8', 'UTF-8, ISO-8859-1');
        $feed = @simplexml_load_string($xml);

        if ($feed === false) {
            return ['postings' => [], 'query' => $query, 'cost_micros' => 0];
        }

        $postings = [];
        $items = $feed->channel->item ?? $feed->entry ?? [];

        foreach ($items as $item) {
            if (count($postings) >= $limit) {
                break;
            }

            $postings[] = [
                'url' => (string) ($item->link ?? $item->id ?? ''),
                'title' => (string) ($item->title ?? ''),
                'snippet' => (string) ($item->description ?? $item->summary ?? ''),
                'employer' => null,
                'location' => null,
                'posted_at' => ($date = (string) ($item->pubDate ?? $item->published ?? '')) !== '' ? date('Y-m-d', strtotime($date) ?: time()) : null,
                'posted_at_source' => 'rss',
                'discovery_channel' => 'board',
            ];
        }

        return ['postings' => $postings, 'query' => $query, 'cost_micros' => 0];
    }
}
