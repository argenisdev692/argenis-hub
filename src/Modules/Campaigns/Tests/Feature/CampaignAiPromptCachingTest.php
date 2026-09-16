<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Infrastructure\Ai\EvaluateCampaignAgent;
use Modules\Campaigns\Infrastructure\Ai\GenerateCampaignAgent;
use Modules\Campaigns\Infrastructure\Ai\LaravelAiCampaignAssistantAdapter;
use Modules\Campaigns\Infrastructure\Ai\LaravelAiCampaignEvaluatorAdapter;
use Modules\Campaigns\Infrastructure\Ai\PreviewCampaignTopicsAgent;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;
use Shared\Infrastructure\Company\CompanyProfile;

/*
| Same contract as the SocialMedia twin: company + geo (long-lived) and the
| brief (short-lived) form the cached prefix; research, iteration feedback
| and past winners stay in the tail. Tavily/Firecrawl short-circuit in
| testing (keys unset), winners return [] without a causer.
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

            $score = static fn (int $value): array => ['value' => $value, 'factors' => [], 'explanation' => 'Scored.'];

            $payload = match ($agentClass) {
                GenerateCampaignAgent::class => [
                    'content' => [
                        'headline' => 'Draft headline',
                        'primary_text' => 'Draft primary text.',
                        'description' => 'Draft description.',
                        'call_to_action' => 'GET_QUOTE',
                        'hashtags' => ['#leads'],
                        'lead_form_questions' => ['What is your budget range?'],
                        'targeting_suggestions' => ['Lookalike of existing customers'],
                    ],
                    'platforms' => [
                        'facebook' => [
                            'adapted_primary_text' => 'Adapted.',
                            'character_count' => 42,
                            'headline' => 'Short headline',
                            'description' => 'Short description',
                            'hashtags' => ['#leads'],
                            'image_concept' => ['title' => 'Concept', 'visual' => 'an icon'],
                        ],
                    ],
                    'cover_image_concept' => ['title' => 'Concept', 'visual' => 'an icon'],
                    'research_sources' => [],
                    'tavily_data_used' => [],
                ],
                EvaluateCampaignAgent::class => [
                    'scores' => [
                        'audience_fit_score' => $score(82),
                        'virality_score' => $score(76),
                        'roi_potential_score' => $score(74),
                        'lead_quality_score' => $score(78),
                        'trend_relevance_score' => $score(79),
                    ],
                    'optimization_suggestions' => [],
                    'ai_detection_risk' => ['value' => 15, 'label' => 'low', 'explanation' => 'Human.'],
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

    $this->brief = static fn (string $provider): GenerateCampaignData => GenerateCampaignData::from([
        'topic' => 'Spring lead-gen push',
        'provider' => $provider,
        'language' => 'en',
        'business_goal' => 'leads',
        'brand_voice' => 'professional',
        'funnel_stage' => 'bofu',
        'platform' => 'both',
        'ad_format' => 'lead_form',
        'generate_images' => false,
    ]);

    $this->weakness = [['score' => 'virality_score', 'current' => 40, 'target' => 70, 'gap' => 30, 'explanation' => 'Flat hook.']];
});

it('sends Anthropic the company and brief as cached system blocks and only research as the message', function (): void {
    $writer = app(LaravelAiCampaignAssistantAdapter::class);

    $writer->generate('test-uuid', ($this->brief)('anthropic'), 1);
    $writer->generate('test-uuid', ($this->brief)('anthropic'), 2, $this->weakness);

    [$first, $second] = $this->client->calls;
    $system = $first['options']['system'];

    expect($system)->toHaveCount(3)
        ->and($system[0]['text'])->toBe((string) app(GenerateCampaignAgent::class)->instructions())
        ->and($system[0]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[1]['text'])->toContain('Company: '.CompanyProfile::data()['name'])
        ->and($system[1]['cache_control'])->toBe(['type' => 'ephemeral', 'ttl' => '1h'])
        ->and($system[2]['text'])->toContain('Topic: Spring lead-gen push')
        ->and($system[2]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($first['prompt'])->toContain('Web research context:')
        ->and($first['prompt'])->not->toContain('Topic: Spring lead-gen push')
        ->and($second['prompt'])->toContain('Flat hook.');

    // The auditor's feedback changes the tail, never the cached prefix.
    expect($second['options']['system'])->toBe($system);
});

it('gives OpenAI one message with an identical prefix across iterations and a per-brief cache key', function (): void {
    $writer = app(LaravelAiCampaignAssistantAdapter::class);

    $writer->generate('test-uuid', ($this->brief)('openai'), 1);
    $writer->generate('test-uuid', ($this->brief)('openai'), 2, $this->weakness);

    [$first, $second] = $this->client->calls;
    $prefixLength = strpos($first['prompt'], 'Web research context:');

    expect($first['options']['prompt_cache_key'])->toStartWith('campaigns-content:')
        ->and($second['options'])->toBe($first['options'])
        ->and($first['prompt'])->toStartWith('Company: ')
        ->and(substr($second['prompt'], 0, $prefixLength))->toBe(substr($first['prompt'], 0, $prefixLength));
});

it('gives the judge an identical cached brief on every iteration and the ad as the tail', function (): void {
    config()->set('ai.default_for_evaluation', 'anthropic');
    $writer = app(LaravelAiCampaignAssistantAdapter::class);
    $judge = app(LaravelAiCampaignEvaluatorAdapter::class);

    $draft = $writer->generate('test-uuid', ($this->brief)('openai'), 1);
    $data = ($this->brief)('openai');

    $judge->evaluate('test-uuid', $draft, $data, 1);
    $judge->evaluate('test-uuid', $draft, $data, 2);

    [, $first, $second] = $this->client->calls;

    expect($first['options']['system'])->toHaveCount(2)
        ->and($first['options']['system'][1]['text'])->toStartWith('BRIEF')
        ->and($second['options']['system'])->toBe($first['options']['system'])
        ->and($first['prompt'])->toStartWith('AD TO SCORE');
});

it('falls over to the next provider when the preferred one fails', function (): void {
    $this->client->failOpenaiOnce = true;

    $draft = app(LaravelAiCampaignAssistantAdapter::class)->generate('test-uuid', ($this->brief)('openai'), 1);

    expect($draft->headline)->toBe('Draft headline')
        ->and($this->client->attemptedProviders)->toBe(['openai', 'anthropic']);
});

it('clears the cache scope after every call', function (): void {
    app(LaravelAiCampaignAssistantAdapter::class)->generate('test-uuid', ($this->brief)('anthropic'), 2, $this->weakness);

    expect(app(PromptCacheScope::class)->current())->toBeNull();
});

it('falls back to one plain message when prompt caching is switched off', function (): void {
    config()->set('ai.prompt_cache.enabled', false);

    app(LaravelAiCampaignAssistantAdapter::class)->generate('test-uuid', ($this->brief)('anthropic'), 2, $this->weakness);

    expect($this->client->calls[0]['options'])->toBe([])
        ->and($this->client->calls[0]['prompt'])->toContain('Topic: Spring lead-gen push');
});

it('streams the angle list as SSE without storing anything', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    PreviewCampaignTopicsAgent::fake(['1. **Angle** — hook (facebook, tofu)']);

    $response = $this->actingAs($admin)
        ->postJson('/campaigns/ai/suggest-topics/stream', ['provider' => 'openai', 'language' => 'en'])
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');

    $this->assertDatabaseCount('campaigns', 0);
});
