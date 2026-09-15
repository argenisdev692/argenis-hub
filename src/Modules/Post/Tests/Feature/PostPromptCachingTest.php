<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Domain\Enums\PostImageMode;
use Modules\Post\Infrastructure\Ai\EvaluatePostContentAgent;
use Modules\Post\Infrastructure\Ai\GeneratePostContentAgent;
use Modules\Post\Infrastructure\Ai\GenerateSocialCopyAgent;
use Modules\Post\Infrastructure\Ai\LaravelAiPostAssistantAdapter;
use Modules\Post\Infrastructure\Ai\LaravelAiPostEvaluatorAdapter;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;
use Shared\Infrastructure\Company\CompanyProfile;

/*
| Every quality-loop iteration re-sends the writer and the judge the same
| instructions, company profile and brief. Those must reach the provider as a
| stable, cacheable prefix; research, judge feedback and the draft change on
| every call and must stay in the tail.
|
| Tavily short-circuits to [] in testing (TAVILY_API_KEY unset).
*/

beforeEach(function (): void {
    Cache::flush();

    $this->client = new class implements AIClientInterface
    {
        /** @var list<array{agent: string, prompt: string, options: array<string, mixed>}> */
        public array $calls = [];

        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null, ?string $model = null, ?int $timeoutSeconds = null): StructuredAgentResponse
        {
            $agent = app($agentClass);

            $this->calls[] = [
                'agent' => $agentClass,
                'prompt' => $prompt,
                'options' => $agent instanceof HasProviderOptions ? $agent->providerOptions((string) $provider) : [],
            ];

            $payload = match ($agentClass) {
                GeneratePostContentAgent::class => [
                    'title' => 'Draft',
                    'content' => '<p>Body.</p>',
                    'excerpt' => 'Excerpt.',
                    'meta_title' => 'Meta title',
                    'meta_description' => 'Meta description',
                    'meta_keywords' => 'kw1, kw2',
                    'cover_image_concept' => ['title' => 'Concept', 'visual' => 'a ledger'],
                    'seo_analysis' => ['primary_keyword' => 'kw1', 'lsi_keywords' => ['kw2']],
                ],
                EvaluatePostContentAgent::class => [
                    'scores' => array_fill_keys(
                        ['human_writing_index', 'eeat_score', 'virality_score', 'roi_score', 'seo_score'],
                        ['value' => 80, 'explanation' => 'Scored.'],
                    ),
                    'eeat_analysis' => [
                        'experience_signals' => [],
                        'expertise_signals' => [],
                        'authoritativeness_signals' => [],
                        'trustworthiness_signals' => [],
                    ],
                    'ai_detection_risk' => 20,
                    'optimization_suggestions' => [],
                ],
                GenerateSocialCopyAgent::class => ['linkedin_post' => 'Post', 'social_caption' => 'Caption', 'hashtags' => ['#ai']],
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

    $this->brief = static fn (string $provider): GeneratePostContentData => new GeneratePostContentData(
        topic: 'AI automation for SMBs',
        provider: $provider,
        angle: 'Cost savings',
        keyTrend: 'Agentic workflows',
        imageMode: PostImageMode::None,
    );

    $this->weakness = [['score' => 'virality_score', 'current' => 40, 'target' => 70, 'gap' => 30, 'explanation' => 'Flat hook.']];
});

it('sends Anthropic the company and brief as cached system blocks and only research and feedback as the message', function (): void {
    $writer = app(LaravelAiPostAssistantAdapter::class);

    $writer->generate(null, ($this->brief)('anthropic'), 1);
    $writer->generate(null, ($this->brief)('anthropic'), 2, $this->weakness);

    [$first, $second] = $this->client->calls;
    $system = $first['options']['system'];

    expect($system)->toHaveCount(3)
        ->and($system[0]['text'])->toBe((string) app(GeneratePostContentAgent::class)->instructions())
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

it('gives the judge an identical cached brief on every iteration and the draft as the tail', function (): void {
    config()->set('ai.default_for_evaluation', 'anthropic');
    $judge = app(LaravelAiPostEvaluatorAdapter::class);

    $draft = static fn (string $title): PostContentDraftData => new PostContentDraftData(
        title: $title,
        content: '<p>Body.</p>',
        excerpt: 'Excerpt.',
        metaTitle: 'Meta',
        metaDescription: 'Description',
        metaKeywords: 'kw',
        coverImageConcept: ['title' => 'Concept', 'visual' => 'ledger'],
        seoAnalysis: ['primary_keyword' => 'kw', 'lsi_keywords' => []],
        provider: 'openai',
    );

    $judge->evaluate(null, $draft('First draft'), ($this->brief)('openai'), 1);
    $judge->evaluate(null, $draft('Second draft'), ($this->brief)('openai'), 2);

    [$first, $second] = $this->client->calls;

    expect($first['options']['system'])->toHaveCount(2)
        ->and($first['options']['system'][1]['text'])->toStartWith('BRIEF')
        ->and($second['options']['system'])->toBe($first['options']['system'])
        ->and($first['prompt'])->toStartWith('DRAFT TO SCORE')
        ->and($first['prompt'])->toContain('First draft')
        ->and($second['prompt'])->toContain('Second draft');
});

it('gives OpenAI one message with an identical prefix across iterations and a per-brief cache key', function (): void {
    $writer = app(LaravelAiPostAssistantAdapter::class);

    $writer->generate(null, ($this->brief)('openai'), 1);
    $writer->generate(null, ($this->brief)('openai'), 2, $this->weakness);

    [$first, $second] = $this->client->calls;
    $prefixLength = strpos($first['prompt'], 'Web research context:');

    expect($first['options']['prompt_cache_key'])->toStartWith('post-content:')
        ->and($second['options'])->toBe($first['options'])
        ->and($first['prompt'])->toStartWith('Company: ')
        ->and(substr($second['prompt'], 0, $prefixLength))->toBe(substr($first['prompt'], 0, $prefixLength));
});

it('keys social copy separately from blog content for the same brief', function (): void {
    $writer = app(LaravelAiPostAssistantAdapter::class);

    $writer->generateSocialCopy(new GenerateContentVariantData(topic: 'AI automation for SMBs', provider: 'openai', angle: 'Cost savings', keyTrend: 'Agentic workflows'));
    $writer->generate(null, ($this->brief)('openai'), 1);

    [$social, $content] = $this->client->calls;

    expect($social['options']['prompt_cache_key'])->toStartWith('post-social:')
        ->and($social['options']['prompt_cache_key'])->not->toBe($content['options']['prompt_cache_key']);
});

it('clears the cache scope after every call', function (): void {
    app(LaravelAiPostAssistantAdapter::class)->generate(null, ($this->brief)('anthropic'), 2, $this->weakness);

    expect(app(PromptCacheScope::class)->current())->toBeNull();
});

it('falls back to one plain message when prompt caching is switched off', function (): void {
    config()->set('ai.prompt_cache.enabled', false);

    app(LaravelAiPostAssistantAdapter::class)->generate(null, ($this->brief)('anthropic'), 2, $this->weakness);

    expect($this->client->calls[0]['options'])->toBe([])
        ->and($this->client->calls[0]['prompt'])->toContain('Topic: AI automation for SMBs');
});
