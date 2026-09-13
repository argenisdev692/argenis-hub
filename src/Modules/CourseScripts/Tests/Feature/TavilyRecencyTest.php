<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Shared\Infrastructure\Research\TavilyClientInterface;

/**
 * The shared Tavily adapter gained an optional recency bias for course
 * research (FR-13d, R10.2). Existing callers must send byte-identical requests.
 */
beforeEach(function (): void {
    config()->set('services.tavily.api_key', 'test-key');
    config()->set('services.tavily.url', 'https://api.tavily.test/search');

    Http::fake(['api.tavily.test/*' => Http::response(['results' => [
        ['title' => 'Result', 'url' => 'https://example.test/a', 'content' => 'Snippet', 'score' => 0.9],
    ]])]);
});

it('sends exactly the original payload when no recency is given', function (): void {
    $results = app(TavilyClientInterface::class)->search(['claude projects']);

    Http::assertSent(fn (Request $request): bool => array_keys($request->data()) === ['query', 'search_depth', 'max_results']);

    expect($results)->toHaveCount(1)->and($results[0]['url'])->toBe('https://example.test/a');
});

it('passes a recency bias as time_range', function (): void {
    app(TavilyClientInterface::class)->search(['claude projects'], 'month');

    Http::assertSent(fn (Request $request): bool => ($request->data()['time_range'] ?? null) === 'month');
});

it('ignores an unknown recency value', function (): void {
    app(TavilyClientInterface::class)->search(['claude projects'], 'decade');

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('time_range', $request->data()));
});
