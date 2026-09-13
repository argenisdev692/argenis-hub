<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Research;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;
use Uri\Rfc3986\Uri;

/**
 * @see FirecrawlClientInterface
 *
 * `POST {base}/scrape` with `formats: ["markdown"]` and `onlyMainContent`
 * (research §10.5). Mirrors {@see TavilyResearchAdapter}: breaker-wrapped,
 * fail-soft, and silent-but-logged on failure.
 */
final readonly class FirecrawlScrapeAdapter implements FirecrawlClientInterface
{
    public function __construct(private CircuitBreakerInterface $breaker) {}

    public function scrape(string $url): ?string
    {
        $apiKey = (string) config('services.firecrawl.api_key');

        if ($apiKey === '' || Uri::parse($url)?->getScheme() !== 'https') {
            return null;
        }

        return $this->breaker->call(
            'firecrawl',
            function () use ($apiKey, $url): ?string {
                $response = Http::withToken($apiKey)
                    ->timeout((int) config('services.firecrawl.timeout', 30))
                    ->retry(1, 500)
                    ->post(rtrim((string) config('services.firecrawl.base_url'), '/').'/scrape', [
                        'url' => $url,
                        'formats' => ['markdown'],
                        'onlyMainContent' => true,
                        'maxAge' => (int) config('services.firecrawl.max_age', 172800000),
                    ]);

                if ($response->failed()) {
                    throw new RuntimeException("Firecrawl scrape failed with status {$response->status()}.");
                }

                $markdown = $response->json('data.markdown');

                return is_string($markdown) && trim($markdown) !== '' ? $markdown : null;
            },
            function (Throwable $e) use ($url): ?string {
                Log::warning('firecrawl.scrape_failed', ['host' => Uri::parse($url)?->getHost(), 'error' => $e->getMessage()]);

                return null;
            },
        );
    }
}
