<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Ports\ScriptWriterPort;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptClosingAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptOutlineAgent;
use Modules\CourseScripts\Infrastructure\Ai\LaravelAiScriptWriterAdapter;
use Modules\CourseScripts\Tests\Support\CanonicalScriptFixture;
use Modules\CourseScripts\Tests\Support\RecordingAiClient;
use Modules\CourseScripts\Tests\Support\WritingContextFixture;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;

/**
 * The ~10 calls of one video share instructions, course context and video
 * context. Those must reach every provider as a stable, cacheable prefix.
 */
beforeEach(function (): void {
    $this->client = RecordingAiClient::install([
        GenerateScriptOutlineAgent::class => CanonicalScriptFixture::outlinePayload(),
        GenerateScriptClosingAgent::class => CanonicalScriptFixture::closingPayload(),
    ]);
    $this->writer = app(LaravelAiScriptWriterAdapter::class);
});

it('sends Anthropic the stable parts as cached system blocks and only the step as the message', function (): void {
    $context = WritingContextFixture::video22();

    $draft = $this->writer->outline($context, 'anthropic');
    $this->writer->closing($context, $draft, 'anthropic');

    [$outline, $closing] = $this->client->calls;
    $system = $outline['options']['system'];

    expect($system)->toHaveCount(3)
        ->and($system[0]['text'])->toBe((string) app(GenerateScriptOutlineAgent::class)->instructions())
        ->and($system[0]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[1]['text'])->toContain('Tecnoform S.A.')
        ->and($system[1]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[2]['text'])->toContain('VIDEO 22')
        ->and($system[2]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($outline['prompt'])->toStartWith('REQUEST: Write the OUTLINE')
        ->and($outline['prompt'])->not->toContain('Tecnoform');

    // The course and video layers are byte-identical between steps.
    expect($closing['options']['system'][1]['text'])->toBe($system[1]['text'])
        ->and($closing['options']['system'][2]['text'])->toBe($system[2]['text']);
});

it('gives OpenAI one message with an identical prefix and a per-course cache key', function (): void {
    $context = WritingContextFixture::video22();

    $draft = $this->writer->outline($context, 'openai');
    $this->writer->closing($context, $draft, 'openai');

    [$outline, $closing] = $this->client->calls;
    $prefixLength = strpos($outline['prompt'], 'REQUEST:');

    expect($outline['options'])->toBe(['prompt_cache_key' => 'course-scripts:'.$context->courseUuid])
        ->and($outline['prompt'])->toStartWith('COURSE LANGUAGE: es')
        ->and(substr($closing['prompt'], 0, $prefixLength))->toBe(substr($outline['prompt'], 0, $prefixLength));
});

it('relies on implicit caching for Gemini', function (): void {
    $this->writer->outline(WritingContextFixture::video22(), 'gemini');

    expect($this->client->calls[0]['options'])->toBe([])
        ->and($this->client->calls[0]['prompt'])->toContain('VIDEO 22');
});

it('clears the cache scope after every call', function (): void {
    $this->writer->outline(WritingContextFixture::video22(), 'anthropic');

    expect(app(PromptCacheScope::class)->current())->toBeNull();
});

it('can be switched off', function (): void {
    config()->set('ai.prompt_cache.enabled', false);

    $this->writer->outline(WritingContextFixture::video22(), 'anthropic');

    expect($this->client->calls[0]['options'])->toBe([])
        ->and($this->client->calls[0]['prompt'])->toContain('Tecnoform S.A.');
});

it('binds the writer port to the caching adapter', function (): void {
    expect(app(ScriptWriterPort::class))->toBeInstanceOf(LaravelAiScriptWriterAdapter::class);
});
