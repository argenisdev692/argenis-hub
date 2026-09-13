<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Shared\Infrastructure\Research\FirecrawlClientInterface;

/**
 * Shared Firecrawl client (FR-13c, research §10.5): fail-soft like Tavily.
 */
beforeEach(function (): void {
    config()->set('services.firecrawl.api_key', 'fc-test');
    config()->set('services.firecrawl.base_url', 'https://api.firecrawl.test/v1');
});

it('returns the page markdown', function (): void {
    Http::fake(['api.firecrawl.test/*' => Http::response(['success' => true, 'data' => ['markdown' => '# Page']])]);

    expect(app(FirecrawlClientInterface::class)->scrape('https://example.test/page'))->toBe('# Page');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.firecrawl.test/v1/scrape'
        && $request->data()['formats'] === ['markdown']
        && $request->data()['onlyMainContent'] === true);
});

it('makes no request without an api key', function (): void {
    config()->set('services.firecrawl.api_key', '');
    Http::fake();

    expect(app(FirecrawlClientInterface::class)->scrape('https://example.test/page'))->toBeNull();

    Http::assertNothingSent();
});

it('refuses non-https urls without a request', function (): void {
    Http::fake();

    expect(app(FirecrawlClientInterface::class)->scrape('http://169.254.169.254/latest'))->toBeNull()
        ->and(app(FirecrawlClientInterface::class)->scrape('not a url'))->toBeNull();

    Http::assertNothingSent();
});

it('returns null on a failed response', function (): void {
    Http::fake(['api.firecrawl.test/*' => Http::response(['success' => false], 500)]);

    expect(app(FirecrawlClientInterface::class)->scrape('https://example.test/page'))->toBeNull();
});
