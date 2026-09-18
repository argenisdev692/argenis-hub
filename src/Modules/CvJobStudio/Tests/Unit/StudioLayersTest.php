<?php

declare(strict_types=1);

use Modules\CvJobStudio\Domain\Services\SlugCandidateGenerator;
use Modules\CvJobStudio\Infrastructure\Ai\CvSnapshotLayer;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;

function snapshotFixture(): array
{
    return [
        'entries' => [
            ['ordinal' => 1, 'organization' => 'Acme', 'role_title' => 'Developer'],
            ['ordinal' => 0, 'organization' => 'Beta', 'role_title' => 'Junior'],
        ],
        'skills' => [
            ['canonical_name' => 'Vue.js', 'evidence' => 'both'],
            ['canonical_name' => 'Laravel', 'evidence' => 'in_bullet'],
        ],
    ];
}

it('renders byte-identical layers for identical input (T-151)', function (): void {
    $first = CvSnapshotLayer::snapshot(snapshotFixture(), 'hash1');
    $second = CvSnapshotLayer::snapshot(snapshotFixture(), 'hash1');

    expect($first->text)->toBe($second->text);

    $cached = CvSnapshotLayer::cached('tailor', $first, 'tail', 'hash1');

    expect($cached->cacheKey)->toBe('cvjs:tailor:hash1')
        ->and($cached->asSingleMessage())->toContain('laravel:in_bullet');
});

it('rejects a long-lived layer after a short-lived one (T-151)', function (): void {
    new CacheablePrompt(
        layers: [PromptLayer::short('tail-ish'), PromptLayer::long('stable')],
        tail: 'tail',
        cacheKey: 'cvjs:test:hash',
    );
})->throws(InvalidArgumentException::class);

it('builds deterministic rubric layers with sorted keys (T-151)', function (): void {
    $first = CvSnapshotLayer::rubric(['vue' => 1, 'laravel' => 2], 'v2');
    $second = CvSnapshotLayer::rubric(['laravel' => 2, 'vue' => 1], 'v2');

    expect($first->text)->toBe($second->text);
});

it('generates probe slugs from company names (T-009)', function (): void {
    $generator = new SlugCandidateGenerator;

    expect($generator->candidates('Möller GmbH'))->toContain('mollergmbh')
        ->and($generator->candidates('Möller GmbH'))->toContain('moller')
        ->and($generator->candidates(''))->toBe([]);
});
