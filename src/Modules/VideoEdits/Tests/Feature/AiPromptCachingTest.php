<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\VideoEdits\Domain\Ports\AiEditAnalysisPort;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Infrastructure\Ai\AnalyzeVideoEditAgent;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;

/**
 * The agent instructions and the script are identical for every take recorded
 * against one script; they must reach each provider as a cacheable prefix.
 */
final class PromptCacheRecordingAiClient implements AIClientInterface
{
    /** @var list<array{prompt: string, options: array<string, mixed>}> */
    public array $calls = [];

    public function generateStructured(string $agentClass, string $prompt, ?string $provider = null): StructuredAgentResponse
    {
        $agent = app($agentClass);

        $this->calls[] = [
            'prompt' => $prompt,
            'options' => $agent instanceof HasProviderOptions ? $agent->providerOptions((string) $provider) : [],
        ];

        return new StructuredAgentResponse(
            invocationId: 'test',
            structured: ['cuts' => [], 'recommendations' => [], 'conclusion' => 'ok'],
            text: '',
            usage: new Usage,
            meta: new Meta(provider: (string) $provider, model: 'test'),
        );
    }

    public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
    {
        return ['base64' => '', 'mime' => 'image/png'];
    }
}

beforeEach(function (): void {
    config()->set('ai.prompt_cache', ['enabled' => true, 'long_ttl' => '1h', 'log_usage' => false]);

    $this->client = new PromptCacheRecordingAiClient;
    app()->instance(AIClientInterface::class, $this->client);

    $this->script = new ScriptDocument('Video 6.1.md', "# Intro\nHablar de Excel.");
    $this->analyze = function (string $provider, ?string $instructions = null, ?Transcript $transcript = null): void {
        config()->set('video-edit.ai.provider', $provider);

        app(AiEditAnalysisPort::class)->analyze(
            $transcript ?? new Transcript([new TranscriptWord('Hoy', 0, 300), new TranscriptWord('PAUSA', 300, 900)]),
            $this->script,
            $instructions,
            15,
        );
    };
});

it('sends Anthropic the instructions and script as long-lived cached system blocks', function (): void {
    ($this->analyze)('anthropic', 'Sé breve.');

    $call = $this->client->calls[0];
    $system = $call['options']['system'];

    expect($system)->toHaveCount(3)
        ->and($system[0]['text'])->toBe((string) app(AnalyzeVideoEditAgent::class)->instructions())
        ->and($system[0]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[1]['text'])->toContain('Hablar de Excel')
        ->and($system[1]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[2]['text'])->toContain('[1] PAUSA')
        ->and($system[2]['cache_control'])->toBe(['type' => 'ephemeral'])
        // Only the per-run part travels as the user message.
        ->and($call['prompt'])->toContain('TARGET DURATION: about 15 minutes')
        ->and($call['prompt'])->toContain('Sé breve.')
        ->and($call['prompt'])->not->toContain('Hablar de Excel');
});

it('gives OpenAI an identical script prefix across takes and a key that does not expose the script', function (): void {
    ($this->analyze)('openai', 'Primera toma.');
    ($this->analyze)('openai', 'Segunda toma.', new Transcript([new TranscriptWord('Otra', 0, 300)]));

    [$first, $second] = $this->client->calls;
    $prefixLength = strpos($first['prompt'], 'NUMBERED TRANSCRIPT:');

    expect($first['prompt'])->toStartWith('SCRIPT “Video 6.1.md”')
        ->and(substr($second['prompt'], 0, $prefixLength))->toBe(substr($first['prompt'], 0, $prefixLength))
        ->and($first['options'])->toBe($second['options'])
        ->and($first['options']['prompt_cache_key'])->toStartWith('video-edits:')
        ->and($first['options']['prompt_cache_key'])->not->toContain('Excel');
});

it('relies on implicit prefix caching for Gemini', function (): void {
    ($this->analyze)('gemini');

    expect($this->client->calls[0]['options'])->toBe([])
        ->and($this->client->calls[0]['prompt'])->toStartWith('SCRIPT')
        ->and($this->client->calls[0]['prompt'])->toContain('[1] PAUSA');
});

it('clears the cache scope after the call', function (): void {
    ($this->analyze)('anthropic');

    expect(app(PromptCacheScope::class)->current())->toBeNull();
});

it('sends the whole prompt as one message when caching is switched off', function (): void {
    config()->set('ai.prompt_cache.enabled', false);

    ($this->analyze)('anthropic');

    expect($this->client->calls[0]['options'])->toBe([])
        ->and($this->client->calls[0]['prompt'])->toContain('Hablar de Excel');
});
