<?php

declare(strict_types=1);

use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Shared\Infrastructure\AI\ProviderFailover;
use Shared\Infrastructure\AI\ResearchReranker;

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
