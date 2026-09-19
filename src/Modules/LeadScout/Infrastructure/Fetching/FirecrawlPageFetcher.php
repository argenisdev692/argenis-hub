<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Fetching;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Ports\PageFetcherPort;
use Modules\LeadScout\Domain\ValueObjects\FetchResult;
use Modules\LeadScout\Infrastructure\Logging\ApplicationLogger;
use RuntimeException;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * Paid extraction step on Firecrawl API **v2** with its OWN client (spec
 * T039, research R15) — the shared adapter targets `/v1`, never pins
 * `proxy` and leaves `storeInCache` on.
 *
 * Every request sends `proxy: "basic"` + `storeInCache: false`, and every
 * response MUST report `metadata.proxyUsed == "basic"`. Anything else →
 * content discarded, `proxy_mismatch` attempt, Firecrawl disabled module-wide
 * until manual review (`FirecrawlPageFetcher::enable()`). Blocked pages are
 * never passed here in the first place (FR-13).
 */
final readonly class FirecrawlPageFetcher implements PageFetcherPort
{
    private const string DISABLED_KEY = 'scout:firecrawl:disabled';

    public function __construct(
        private OutboundUrlGuard $guard,
        private CircuitBreakerInterface $breaker,
    ) {}

    public static function isDisabled(): bool
    {
        return (bool) Cache::get(self::DISABLED_KEY, false);
    }

    public static function disable(string $reason): void
    {
        Cache::forever(self::DISABLED_KEY, true);
        Log::warning('lead-scout.firecrawl_disabled', ApplicationLogger::redact(['reason' => $reason]));
    }

    public static function enable(): void
    {
        Cache::forget(self::DISABLED_KEY);
    }

    public function fetch(string $url): FetchResult
    {
        try {
            $this->guard->assertAllowed($url);
        } catch (InvalidArgumentException $e) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: $e->getMessage());
        }

        if (self::isDisabled()) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: 'firecrawl disabled pending manual review');
        }

        $apiKey = (string) config('services.firecrawl.api_key');

        if ($apiKey === '') {
            return new FetchResult($url, $url, FetchStatus::Failed, error: 'missing FIRECRAWL_API_KEY');
        }

        try {
            return $this->breaker->call(
                'lead-scout:firecrawl',
                fn (): FetchResult => $this->scrape($apiKey, $url),
                fn (Throwable $e): FetchResult => new FetchResult($url, $url, FetchStatus::Failed, error: mb_substr($e->getMessage(), 0, 200)),
            );
        } catch (Throwable $e) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: mb_substr($e->getMessage(), 0, 200));
        }
    }

    private function scrape(string $apiKey, string $url): FetchResult
    {
        $base = rtrim((string) config('lead-scout.fetching.firecrawl_v2_url', 'https://api.firecrawl.dev/v2'), '/');

        $response = Http::withToken($apiKey)
            ->timeout((int) config('lead-scout.fetching.firecrawl_timeout', 60))
            ->retry(1, 500, throw: false)
            ->post("{$base}/scrape", [
                'url' => $url,
                'formats' => ['markdown', 'html'],
                'onlyMainContent' => true,
                'proxy' => 'basic',
                'storeInCache' => false,
            ]);

        if ($response->failed()) {
            $status = $response->status();

            if (in_array($status, [401, 403, 429], true)) {
                return new FetchResult($url, $url, FetchStatus::Blocked, error: "firecrawl http {$status}");
            }

            throw new RuntimeException("Firecrawl scrape failed with status {$status}.");
        }

        $payload = $response->json();

        if (($payload['metadata']['proxyUsed'] ?? null) !== 'basic') {
            self::disable('proxyUsed='.var_export($payload['metadata']['proxyUsed'] ?? null, true));

            return new FetchResult($url, $url, FetchStatus::ProxyMismatch, error: 'proxyUsed was not basic');
        }

        $markdown = $payload['data']['markdown'] ?? null;
        $html = $payload['data']['html'] ?? null;

        if (! is_string($markdown) || trim($markdown) === '') {
            return new FetchResult($url, $url, FetchStatus::Failed, error: 'empty markdown');
        }

        return new FetchResult($url, $url, FetchStatus::Ok, markdown: $markdown, html: is_string($html) ? $html : null);
    }
}
