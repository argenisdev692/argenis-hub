<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Promptable;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;
use Shared\Infrastructure\AI\PromptCache\UsesPromptCache;

final class PromptCacheProbeAgent implements Agent, HasProviderOptions
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): string
    {
        return 'STABLE INSTRUCTIONS';
    }
}

beforeEach(function (): void {
    config()->set('ai.prompt_cache', ['enabled' => true, 'long_ttl' => '1h', 'log_usage' => false]);
    $this->scope = app(PromptCacheScope::class);
    $this->agent = new PromptCacheProbeAgent;
});

afterEach(fn () => $this->scope->clear());

it('rejects a short-lived layer placed before a long-lived one', function (): void {
    new CacheablePrompt([PromptLayer::short('video'), PromptLayer::long('course')], 'tail', 'key');
})->throws(InvalidArgumentException::class);

it('joins layers and tail into one message for providers without system blocks', function (): void {
    $prompt = new CacheablePrompt([PromptLayer::long('course'), PromptLayer::short(''), PromptLayer::short('video')], 'tail', 'key');

    expect($prompt->asSingleMessage())->toBe("course\n\nvideo\n\ntail");
});

it('returns no options outside a cache scope or when disabled', function (): void {
    expect($this->agent->providerOptions('anthropic'))->toBe([]);

    $this->scope->set(new CacheablePrompt([PromptLayer::long('course')], 'tail', 'key'));
    config()->set('ai.prompt_cache.enabled', false);

    expect($this->agent->providerOptions('anthropic'))->toBe([]);
});

it('caps Anthropic at four breakpoints by merging extra layers into the last block', function (): void {
    $this->scope->set(new CacheablePrompt([
        PromptLayer::long('one'),
        PromptLayer::long('two'),
        PromptLayer::short('three'),
        PromptLayer::short('four'),
    ], 'tail', 'key'));

    $system = $this->agent->providerOptions('anthropic')['system'];

    expect($system)->toHaveCount(4)
        ->and($system[0])->toBe(['type' => 'text', 'text' => 'STABLE INSTRUCTIONS', 'cache_control' => ['type' => 'ephemeral', 'ttl' => '1h']])
        ->and($system[1]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[3]['text'])->toBe("three\n\nfour")
        ->and($system[3]['cache_control'])->toBe(['type' => 'ephemeral']);
});

it('uses the default TTL when the long TTL is not one hour', function (): void {
    config()->set('ai.prompt_cache.long_ttl', '5m');
    $this->scope->set(new CacheablePrompt([PromptLayer::long('course')], 'tail', 'key'));

    expect($this->agent->providerOptions('anthropic')['system'][1]['cache_control'])->toBe(['type' => 'ephemeral']);
});

it('sends OpenAI a cache key truncated to 64 characters and Gemini nothing', function (): void {
    $this->scope->set(new CacheablePrompt([PromptLayer::long('course')], 'tail', str_repeat('k', 80)));

    expect($this->agent->providerOptions('openai'))->toBe(['prompt_cache_key' => str_repeat('k', 64)])
        ->and($this->agent->providerOptions('gemini'))->toBe([]);
});
