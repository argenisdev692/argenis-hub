<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Shared\Infrastructure\Research\TavilyClientInterface;

beforeEach(function (): void {
    config()->set('services.tavily.api_key', 'test-key');
    config()->set('services.tavily.url', 'https://api.tavily.com/search');
});

it('sends the per-call search depth instead of the configured default', function (): void {
    config()->set('services.tavily.search_depth', 'advanced');
    Http::fake(['*' => Http::response(['results' => []], 200)]);

    app(TavilyClientInterface::class)->search(['acme lisboa sitio oficial'], searchDepth: 'basic');

    Http::assertSent(static fn (Request $request): bool => $request['search_depth'] === 'basic');
});

it('falls back to the configured default for an unknown depth', function (): void {
    config()->set('services.tavily.search_depth', 'advanced');
    Http::fake(['*' => Http::response(['results' => []], 200)]);

    app(TavilyClientInterface::class)->search(['acme lisboa sitio oficial'], searchDepth: 'ultra');

    Http::assertSent(static fn (Request $request): bool => $request['search_depth'] === 'advanced');
});
