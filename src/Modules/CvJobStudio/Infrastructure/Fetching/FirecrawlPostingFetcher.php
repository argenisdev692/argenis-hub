<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Fetching;

use Modules\CvJobStudio\Domain\Ports\PostingTextFetcherPort;
use Modules\CvJobStudio\Domain\Services\RobotsTxtPolicy;
use Shared\Infrastructure\Research\FirecrawlClientInterface;

/**
 * Firecrawl fetch (T-159, SC-20): ALWAYS `proxy: "basic"` — a config guard
 * rejects any other value at boot, a refusal in basic mode marks the page
 * NOT fetchable and is never retried in auto/stealth/enhanced. Spec §8 bans
 * circumventing anti-bot measures; V-5 caught Firecrawl's automatic mode
 * picking a stealth proxy once.
 */
final readonly class FirecrawlPostingFetcher implements PostingTextFetcherPort
{
    public const string PROXY_MODE = 'basic';

    public function __construct(
        private FirecrawlClientInterface $firecrawl,
        private OutboundUrlGuard $guard,
        private RobotsTxtPolicy $robots,
    ) {
        $configured = (string) config('services.firecrawl.proxy', self::PROXY_MODE);

        if ($configured !== self::PROXY_MODE) {
            throw new \InvalidArgumentException('Firecrawl proxy must be "basic" (T-159).');
        }
    }

    public function stepName(): string
    {
        return 'firecrawl';
    }

    public function fetch(string $url): ?array
    {
        if (! $this->guard->allowed($url) || ! $this->robots->mayFetch($url)) {
            return null;
        }

        $text = $this->firecrawl->scrape($url, self::PROXY_MODE);

        if ($text === null || trim($text) === '') {
            return null;
        }

        return ['text' => $text, 'completeness' => 'full', 'cost_micros' => (int) round((float) config('cv-job-studio.firecrawl_scrape_eur', 0) * 1_000_000)];
    }
}
