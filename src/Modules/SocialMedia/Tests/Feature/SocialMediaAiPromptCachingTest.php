<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Infrastructure\Ai\EvaluateSocialMediaContentAgent;
use Modules\SocialMedia\Infrastructure\Ai\GenerateSocialMediaContentAgent;
use Modules\SocialMedia\Infrastructure\Ai\LaravelAiSocialMediaAssistantAdapter;
use Modules\SocialMedia\Infrastructure\Ai\LaravelAiSocialMediaEvaluatorAdapter;
use Modules\SocialMedia\Infrastructure\Ai\PreviewSocialMediaTopicsAgent;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;
use Shared\Infrastructure\Company\CompanyProfile;

/*
| The writer prompt is company (long-lived) + brief (short-lived) as the
| cached prefix; research, iteration feedback and past winners stay in the
| tail. Tavily/Firecrawl short-circuit in testing (keys unset), the winners
| retriever returns [] without a causer — so the tail here is research-only
| and the prefix assertions stay deterministic.
*/

beforeEach(function (): void {
    Cache::flush();

    $this->client = new class implements AIClientInterface
    {
        /** @var list<array{agent: string, prompt: string, provider: ?string, options: array<string, mixed>}> */
        public array $calls = [];

        /** @var list<string> */
        public array $attemptedProviders = [];

        public bool $failOpenaiOnce = false;

        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null, ?string $model = null, ?int $timeoutSeconds = null): StructuredAgentResponse
        {
            $agent = app($agentClass);

            $this->calls[] = [
                'agent' => $agentClass,
                'prompt' => $prompt,
                'provider' => $provider,
                'options' => $agent instanceof HasProviderOptions ? $agent->providerOptions((string) $provider) : [],
            ];

            if ($this->failOpenaiOnce && $provider === 'openai') {
                $this->attemptedProviders[] = $provider;

                throw new RuntimeException('openai is down');
            }

            $this->attemptedProviders[] = (string) $provider;

            $payload = match ($agentClass) {
                GenerateSocialMediaContentAgent::class => [
                    'content' => [
                        'headline' => 'Draft headline',
                        'body' => 'Draft body.',
                        'call_to_action' => 'Follow.',
                        'hashtags' => ['#tech'],
                    ],
                    'platforms' => [
                        'linkedin' => [
                            'adapted_content' => 'Adapted.',
                            'character_count' => 42,
                            'hashtags' => ['#tech'],
                            'image_concept' => ['title' => 'Concept', 'visual' => 'a ledger', 'route' => 'a', 'svg_steps' => []],
                        ],
                    ],
                    'cover_image_concept' => ['title' => 'Concept', 'visual' => 'a ledger', 'route' => 'a', 'svg_steps' => []],
                    'research_sources' => [],
                    'tavily_data_used' => [],
                ],
                EvaluateSocialMediaContentAgent::class => [
                    'scores' => array_fill_keys(
                        ['human_writing_index', 'virality_score', 'engagement_score', 'roi_score', 'trend_alignment'],
                        ['value' => 80, 'factors' => [], 'explanation' => 'Scored.'],
                    ),
                    'eeat_analysis' => [
                        'experience_signals' => [],
                        'expertise_signals' => [],
                        'authoritativeness_signals' => [],
                        'trustworthiness_signals' => [],
                    ],
                    'ai_detection_risk' => ['value' => 10, 'label' => 'low', 'explanation' => 'Human.'],
                    'optimization_suggestions' => [],
                ],
                default => throw new RuntimeException("No canned payload for {$agentClass}."),
            };

            return new StructuredAgentResponse(
                invocationId: 'test',
                structured: $payload,
                text: '',
                usage: new Usage,
                meta: new Meta(provider: (string) $provider, model: 'test'),
            );
        }

        public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
        {
            return ['base64' => '', 'mime' => 'image/png'];
        }
    };

    app()->instance(AIClientInterface::class, $this->client);

    $this->brief = static fn (string $provider): GenerateSocialMediaContentData => GenerateSocialMediaContentData::from([
        'topic' => 'AI automation for SMBs',
        'provider' => $provider,
        'language' => 'en',
        'business_goal' => 'awareness',
        'brand_voice' => 'professional',
        'funnel_stage' => 'tofu',
        'angle' => 'Cost savings',
        'key_trend' => 'Agentic workflows',
        'image_mode' => 'none',
        'generate_voiceover' => false,
    ]);

    $this->weakness = [['score' => 'virality_score', 'current' => 40, 'target' => 70, 'gap' => 30, 'explanation' => 'Flat hook.']];
});

it('sends Anthropic the company and brief as cached system blocks and only research as the message', function (): void {
    $writer = app(LaravelAiSocialMediaAssistantAdapter::class);

    $writer->generate('test-uuid', ($this->brief)('anthropic'), 1);
    $writer->generate('test-uuid', ($this->brief)('anthropic'), 2, $this->weakness);

    [$first, $second] = $this->client->calls;
    $system = $first['options']['system'];

    expect($system)->toHaveCount(3)
        ->and($system[0]['text'])->toBe((string) app(GenerateSocialMediaContentAgent::class)->instructions())
        ->and($system[0]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[1]['text'])->toContain('Company: '.CompanyProfile::data()['name'])
        ->and($system[1]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[2]['text'])->toContain('Topic: AI automation for SMBs')
        ->and($system[2]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($first['prompt'])->toStartWith('Web research context:')
        ->and($first['prompt'])->not->toContain('Topic:')
        ->and($second['prompt'])->toContain('Flat hook.');

    // The judge's feedback changes the tail, never the cached prefix.
    expect($second['options']['system'])->toBe($system);
});

it('gives OpenAI one message with an identical prefix across iterations and a per-brief cache key', function (): void {
    $writer = app(LaravelAiSocialMediaAssistantAdapter::class);

    $writer->generate('test-uuid', ($this->brief)('openai'), 1);
    $writer->generate('test-uuid', ($this->brief)('openai'), 2, $this->weakness);

    [$first, $second] = $this->client->calls;
    $prefixLength = strpos($first['prompt'], 'Web research context:');

    expect($first['options']['prompt_cache_key'])->toStartWith('social-media-content:')
        ->and($second['options'])->toBe($first['options'])
        ->and($first['prompt'])->toStartWith('Company: ')
        ->and(substr($second['prompt'], 0, $prefixLength))->toBe(substr($first['prompt'], 0, $prefixLength));
});

it('gives the judge an identical cached brief on every iteration and the draft as the tail', function (): void {
    config()->set('ai.default_for_evaluation', 'anthropic');
    $writer = app(LaravelAiSocialMediaAssistantAdapter::class);
    $judge = app(LaravelAiSocialMediaEvaluatorAdapter::class);

    $draft = $writer->generate('test-uuid', ($this->brief)('openai'), 1);
    $data = ($this->brief)('openai');

    $judge->evaluate('test-uuid', $draft, $data, 1);
    $judge->evaluate('test-uuid', $draft, $data, 2);

    [, $first, $second] = $this->client->calls;

    expect($first['options']['system'])->toHaveCount(2)
        ->and($first['options']['system'][1]['text'])->toStartWith('BRIEF')
        ->and($second['options']['system'])->toBe($first['options']['system'])
        ->and($first['prompt'])->toStartWith('DRAFT TO SCORE');
});

it('falls over to the next provider when the preferred one fails', function (): void {
    $this->client->failOpenaiOnce = true;

    $draft = app(LaravelAiSocialMediaAssistantAdapter::class)->generate('test-uuid', ($this->brief)('openai'), 1);

    expect($draft->headline)->toBe('Draft headline')
        ->and($this->client->attemptedProviders)->toBe(['openai', 'anthropic']);
});

it('clears the cache scope after every call', function (): void {
    app(LaravelAiSocialMediaAssistantAdapter::class)->generate('test-uuid', ($this->brief)('anthropic'), 2, $this->weakness);

    expect(app(PromptCacheScope::class)->current())->toBeNull();
});

it('falls back to one plain message when prompt caching is switched off', function (): void {
    config()->set('ai.prompt_cache.enabled', false);

    app(LaravelAiSocialMediaAssistantAdapter::class)->generate('test-uuid', ($this->brief)('anthropic'), 2, $this->weakness);

    expect($this->client->calls[0]['options'])->toBe([])
        ->and($this->client->calls[0]['prompt'])->toContain('Topic: AI automation for SMBs');
});

it('streams the topic list as SSE without storing anything', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    PreviewSocialMediaTopicsAgent::fake(['1. **Idea** — hook (linkedin, tofu)']);

    $response = $this->actingAs($admin)
        ->postJson('/social-media/ai/suggest-topics/stream', ['provider' => 'openai', 'language' => 'en'])
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');

    $this->assertDatabaseCount('social_media_contents', 0);
});
