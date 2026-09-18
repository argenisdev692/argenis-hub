<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Fetching;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Domain\Ports\PageFetcherPort;
use Modules\LeadScout\Domain\ValueObjects\FetchResult;

/**
 * Cheapest ladder step (spec FR-11/FR-13): honest HTTP with the module
 * User-Agent, 5 s timeout, 2 retries on network/5xx only. Block answers
 * (401/403/429, CAPTCHA/anti-bot markers, login walls) come back `blocked`
 * and are NEVER escalated to another provider. Only `text/html` under
 * 2 MB is read; empty JS shells report `spaEmpty` for the Firecrawl step.
 */
final readonly class DirectHttpPageFetcher implements PageFetcherPort
{
    /**
     * @var list<string>
     */
    private const array BLOCK_MARKERS = [
        'captcha', 'cf-challenge', 'checking your browser', 'access denied',
        'request blocked', 'are you a robot', 'datadome', 'perimeterx', 'arkose',
        'verify you are human', 'unusual traffic',
    ];

    public function __construct(
        private OutboundUrlGuard $guard,
        private int $timeoutSeconds = 5,
        private int $maxBytes = 2_000_000,
    ) {}

    public function fetch(string $url): FetchResult
    {
        try {
            $this->guard->assertAllowed($url);
        } catch (InvalidArgumentException $e) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: $e->getMessage());
        }

        try {
            $response = Http::withHeaders(['User-Agent' => (string) config('lead-scout.fetching.user_agent')])
                ->timeout($this->timeoutSeconds)
                ->retry(2, 200, throw: false)
                ->get($url);
        } catch (ConnectionException $e) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: 'network: '.mb_substr($e->getMessage(), 0, 200));
        } catch (\Throwable $e) {
            return new FetchResult($url, $url, FetchStatus::Failed, error: mb_substr($e->getMessage(), 0, 200));
        }

        $status = $response->status();

        if (in_array($status, [401, 403, 429], true)) {
            return new FetchResult($url, $this->finalUrl($response, $url), FetchStatus::Blocked, error: "http {$status}");
        }

        if ($status >= 500) {
            return new FetchResult($url, $this->finalUrl($response, $url), FetchStatus::Failed, error: "http {$status}");
        }

        $contentType = mb_strtolower((string) $response->header('Content-Type'));

        // HTML pages plus XML feeds/sitemaps (the sitemap step parses <loc>).
        if ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'xml')) {
            return new FetchResult($url, $this->finalUrl($response, $url), FetchStatus::Failed, error: "content-type {$contentType}");
        }

        $html = $response->body();

        if (strlen($html) > $this->maxBytes) {
            return new FetchResult($url, $this->finalUrl($response, $url), FetchStatus::Failed, error: 'body over 2 MB');
        }

        if ($this->isBlockedBody($html)) {
            return new FetchResult($url, $this->finalUrl($response, $url), FetchStatus::Blocked, error: 'challenge marker');
        }

        $markdown = $this->toMarkdown($html);

        if (trim($markdown) === '' && $this->looksLikeSpa($html)) {
            return new FetchResult($url, $this->finalUrl($response, $url), FetchStatus::Ok, html: null, error: 'spa_empty', spaEmpty: true);
        }

        return new FetchResult($url, $this->finalUrl($response, $url), FetchStatus::Ok, markdown: $markdown, html: $html);
    }

    private function isBlockedBody(string $html): bool
    {
        $lower = mb_strtolower(mb_substr($html, 0, 20000));

        foreach (self::BLOCK_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        // A page whose substance is a login form is a login wall, not content.
        return preg_match('/<input[^>]*type\s*=\s*["\']?password/i', $html) === 1
            && preg_match('/sign in|log in|iniciar sesi|acceder/i', $lower) === 1;
    }

    private function looksLikeSpa(string $html): bool
    {
        return preg_match('/id\s*=\s*["\'](app|root)["\']|__NEXT_DATA__|ng-app|data-reactroot/i', $html) === 1;
    }

    #[\NoDiscard('Markdown must be captured')]
    public static function toMarkdown(string $html): string
    {
        $text = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $text = (string) preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text);
        $text = (string) preg_replace('/<nav\b[^>]*>.*?<\/nav>/is', ' ', $text);
        $text = (string) preg_replace('/<footer\b[^>]*>.*?<\/footer>/is', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        $text = (string) preg_replace('/\n\s*\n+/', "\n\n", $text);

        return trim($text);
    }

    private function finalUrl(mixed $response, string $fallback): string
    {
        try {
            $stats = $response->handlerStats();

            if (is_array($stats) && isset($stats['url']) && is_string($stats['url']) && $stats['url'] !== '') {
                return $stats['url'];
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }
}
