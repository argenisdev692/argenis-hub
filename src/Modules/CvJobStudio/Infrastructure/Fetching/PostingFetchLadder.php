<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Fetching;

use Modules\CvJobStudio\Domain\Ports\PostingTextFetcherPort;

/**
 * Extraction ladder (T-044, FR-14): direct HTTP → Firecrawl, recording which
 * step produced the text. Never runs when `text_source = source_api` or the
 * access mode is link_only/resolve_only — those stay references (T-158).
 */
final readonly class PostingFetchLadder
{
    /** @param  list<PostingTextFetcherPort>  $fetchers */
    public function __construct(private array $fetchers) {}

    /**
     * @return array{text: string, ladder_step: string, completeness: string, cost_micros: int}|null
     */
    #[\NoDiscard]
    public function fetch(string $url, bool $skipExtraction): ?array
    {
        if ($skipExtraction) {
            return null;
        }

        foreach ($this->fetchers as $fetcher) {
            $result = $fetcher->fetch($url);

            if ($result !== null) {
                return [
                    'text' => $result['text'],
                    'ladder_step' => $fetcher->stepName(),
                    'completeness' => $result['completeness'],
                    'cost_micros' => $result['cost_micros'],
                ];
            }
        }

        return null;
    }
}
