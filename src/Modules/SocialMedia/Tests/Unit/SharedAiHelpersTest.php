<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\Gateway;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Shared\Infrastructure\AI\ProviderFailover;
use Shared\Infrastructure\AI\ResearchReranker;

/*
| These guard the shared AI wiring, not one module's behaviour.
|
| The model ids used to be decided by laravel/ai itself: the provider configs
| carried no `models` key, so the driver fell back to a model id hardcoded
| inside the package. `ANTHROPIC_TEXT_MODEL` / `GEMINI_TEXT_MODEL` sat in
| `.env` matching those defaults by coincidence and were read by nothing. A
| `composer update` that bumped the package default would have changed the
| model in production with no diff in this repository.
*/

it('takes the text model from config instead of the package default', function (): void {
    foreach (['anthropic', 'gemini'] as $provider) {
        expect(config("ai.providers.{$provider}.models.text.default"))
            ->toBeString()
            ->not->toBe('');
    }
});

it('binds the configured model ids to the env keys that name them', function (): void {
    // The property that was missing, asserted against the driver itself: hand
    // the provider a config with a model id the package does not know, and it
    // must hand that id back. Before the wiring this always returned the id
    // hardcoded inside laravel/ai.
    $provider = new GeminiProvider(
        gateway: Mockery::mock(Gateway::class),
        config: ['models' => ['text' => ['default' => 'gemini-9-flash']]],
        events: Mockery::mock(Dispatcher::class),
    );

    expect($provider->defaultTextModel())->toBe('gemini-9-flash')
        ->and(config('ai.providers.gemini.models.text.default'))->toBeString();
});

it('keeps the gemini base url on a versioned path', function (): void {
    // The driver appends `models/{model}:generateContent`. A bare host makes
    // every call 404, which reads exactly like a bad key or a lapsed billing
    // account and is neither.
    expect(config('ai.providers.gemini.url'))
        ->toMatch('#^https://generativelanguage\.googleapis\.com/v1[a-z]*/$#');
});

it('prefers the requested provider, then follows the configured order without duplicates', function (): void {
    config()->set('ai.failover_order', 'openai,anthropic,gemini');

    $failover = app(ProviderFailover::class);

    expect($failover->attempts('anthropic'))->toBe(['anthropic', 'openai', 'gemini'])
        ->and($failover->attempts('OPENAI'))->toBe(['openai', 'anthropic', 'gemini'])
        ->and($failover->attempts(null))->toBe(['openai', 'anthropic', 'gemini'])
        ->and($failover->attempts('groq'))->toBe(['groq', 'openai', 'anthropic', 'gemini']);
});

it('falls back to openai when nothing is configured', function (): void {
    config()->set('ai.failover_order', '');

    expect(app(ProviderFailover::class)->attempts(null))->toBe(['openai']);
});

it('reorders research by relevance and maps rows back by index', function (): void {
    Reranking::fake([
        [
            new RankedDocument(index: 1, document: 'b', score: 0.9),
            new RankedDocument(index: 0, document: 'a', score: 0.1),
        ],
    ]);

    $rows = [
        ['title' => 'A', 'url' => 'https://a.test', 'content' => 'a', 'score' => 0.9],
        ['title' => 'B', 'url' => 'https://b.test', 'content' => 'b', 'score' => 0.1],
    ];

    $ordered = app(ResearchReranker::class)->rerank('query', $rows);

    expect(array_column($ordered, 'title'))->toBe(['B', 'A']);
});

it('returns research untouched when the reranker fails', function (): void {
    Reranking::fake(static function (): never {
        throw new RuntimeException('reranker is down');
    });

    $rows = [
        ['title' => 'A', 'url' => 'https://a.test', 'content' => 'a', 'score' => 0.9],
    ];

    expect(app(ResearchReranker::class)->rerank('query', $rows))->toBe($rows)
        ->and(app(ResearchReranker::class)->rerank('query', []))->toBe([]);
});
