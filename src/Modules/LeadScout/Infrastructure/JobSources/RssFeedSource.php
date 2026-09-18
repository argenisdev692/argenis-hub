<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\JobSources;

use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Application\DTOs\RawPostingData;
use Modules\LeadScout\Domain\Enums\SourceType;
use Modules\LeadScout\Domain\Ports\JobSourcePort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;

/**
 * RSS postings (LaraJobs, Remotive, We Work Remotely — spec US-2, research
 * R5). Remotive terms honored: poll at most 4×/day (seeder frequency),
 * attribution «vía Remotive» travels on the pivot source row, and nothing
 * is ever republished (FR-30) — only normalized internally.
 */
final readonly class RssFeedSource implements JobSourcePort
{
    public function supports(ScoutSourceEloquentModel $source): bool
    {
        return $source->type === SourceType::Rss;
    }

    public function fetchSince(ScoutSourceEloquentModel $source, ?string $cursor): iterable
    {
        $feeds = (array) config('lead-scout.job_sources.rss_feeds', []);
        $url = $feeds[$source->name] ?? null;

        if (! is_string($url) || $url === '') {
            return;
        }

        $response = Http::timeout(15)->retry(1, 500)->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("RSS fetch failed with status {$response->status()} for {$source->name}.");
        }

        yield from self::parseItems($response->body());
    }

    /**
     * @return iterable<int, RawPostingData>
     */
    public static function parseItems(string $xml): iterable
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $feed = simplexml_load_string($xml);
        } finally {
            libxml_use_internal_errors($previous);
        }

        if ($feed === false) {
            return;
        }

        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                yield self::fromRssItem($item);
            }

            return;
        }

        if (isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                yield self::fromAtomEntry($entry);
            }
        }
    }

    private static function fromRssItem(\SimpleXMLElement $item): RawPostingData
    {
        $namespaces = $item->getNamespaces(true);
        $creator = '';

        if (isset($namespaces['dc'])) {
            $dc = $item->children($namespaces['dc']);
            $creator = trim((string) ($dc->creator ?? ''));
        }

        $title = trim((string) $item->title);

        return new RawPostingData(
            title: $title,
            companyName: $creator !== '' ? $creator : self::companyFromTitle($title),
            location: null,
            country: null,
            remoteMode: str_contains(mb_strtolower($title.' '.(string) $item->description), 'remot') ? 'remote' : null,
            contractType: null,
            language: null,
            publishedAt: trim((string) $item->pubDate) !== '' ? trim((string) $item->pubDate) : null,
            sourceUrl: trim((string) $item->link),
            bodyText: trim((string) $item->description) !== '' ? trim((string) $item->description) : null,
        );
    }

    private static function fromAtomEntry(\SimpleXMLElement $entry): RawPostingData
    {
        $title = trim((string) $entry->title);
        $link = '';

        foreach ($entry->link as $candidate) {
            $rel = (string) ($candidate['rel'] ?? 'alternate');

            if ($rel === 'alternate') {
                $link = (string) ($candidate['href'] ?? '');
            }
        }

        return new RawPostingData(
            title: $title,
            companyName: trim((string) ($entry->author->name ?? '')) !== '' ? trim((string) $entry->author->name) : self::companyFromTitle($title),
            location: null,
            country: null,
            remoteMode: null,
            contractType: null,
            language: null,
            publishedAt: trim((string) ($entry->published ?? $entry->updated ?? '')) !== '' ? trim((string) ($entry->published ?? $entry->updated)) : null,
            sourceUrl: $link,
            bodyText: trim((string) ($entry->summary ?? $entry->content ?? '')) !== '' ? trim((string) ($entry->summary ?? $entry->content)) : null,
        );
    }

    #[\NoDiscard('Parsed company name must be captured')]
    public static function companyFromTitle(string $title): string
    {
        foreach ([' @ ', ' at ', ' — ', ' – ', ' - '] as $separator) {
            $parts = explode($separator, $title);

            if (count($parts) > 1 && trim((string) array_last($parts)) !== '') {
                return trim((string) array_last($parts));
            }
        }

        return 'Unknown';
    }
}
