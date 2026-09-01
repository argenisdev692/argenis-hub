<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Ai\EvaluatePostContentAgent;
use Modules\Post\Infrastructure\Ai\GeneratePostContentAgent;
use Shared\Infrastructure\AI\AIClientInterface;

/*
| The economics of the quality loop, pinned as tests.
|
| Two properties are worth real money and neither is visible from the JSON
| response, so nothing but a test can protect them:
|
|   1. The cover image is billed ONCE per generation, on the winning draft —
|      not once per iteration. A 5-iteration run used to be a 5-image run.
|   2. The judge runs on a DIFFERENT provider from the writer, so the gate is
|      an outside opinion rather than the writer marking its own homework.
|
| `QUEUE_CONNECTION=sync` in phpunit.xml means GeneratePostContentJob runs
| inline inside the accepting request, so these still drive the whole pipeline
| through one HTTP call — they just read the outcome off the generation row
| instead of the response body.
|
| Tavily is not faked explicitly — TAVILY_API_KEY is unset in the testing
| environment, so TavilyResearchAdapter::search() short-circuits to an empty
| array without any HTTP call.
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config()->set('ai.default_for_evaluation', 'anthropic');

    // `throttle:5,1` on generate-content is keyed by user id, and RefreshDatabase
    // reuses ids across tests while the cache store survives them — so without
    // this the fifth accepted generation in the FILE fails, not the fifth in a
    // test. Flushing here (not mid-test) leaves the AI result cache a test
    // writes for itself untouched, which the image-mode case depends on.
    Cache::flush();
});

function postCostAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function postCostDraft(string $title = 'Cost Draft'): array
{
    return [
        'title' => $title,
        'content' => 'Body.',
        'excerpt' => 'Excerpt.',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
        'meta_keywords' => 'kw1, kw2',
        'cover_image_concept' => ['title' => 'Cost Concept', 'visual' => 'a glowing ledger'],
        'seo_analysis' => ['primary_keyword' => 'cost', 'lsi_keywords' => ['kw1']],
    ];
}

/**
 * @param  array<string, int>  $overrides
 * @return array<string, mixed>
 */
function postCostVerdict(array $overrides = []): array
{
    $scores = [
        'human_writing_index' => 82,
        'eeat_score' => 75,
        'virality_score' => 76,
        'roi_score' => 74,
        'seo_score' => 80,
        ...$overrides,
    ];

    return [
        'scores' => array_map(
            static fn (int $value): array => ['value' => $value, 'explanation' => 'Scored.'],
            $scores,
        ),
        'eeat_analysis' => [
            'experience_signals' => [],
            'expertise_signals' => [],
            'authoritativeness_signals' => [],
            'trustworthiness_signals' => [],
        ],
        'ai_detection_risk' => 20,
        'optimization_suggestions' => ['Add data.'],
    ];
}

/**
 * Records every structured call as `agent => provider` and every image prompt,
 * without billing either. Returns the spy so a test can count both.
 */
function spyOnPostAiCalls(): object
{
    Storage::fake((string) config('filesystems.cloud', 'r2'));

    $spy = new class
    {
        /** @var list<array{agent: string, provider: ?string}> */
        public array $structured = [];

        /** @var list<string> */
        public array $images = [];
    };

    $inner = app(AIClientInterface::class);

    app()->instance(AIClientInterface::class, new class($inner, $spy) implements AIClientInterface
    {
        public function __construct(private AIClientInterface $inner, private object $spy) {}

        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null): StructuredAgentResponse
        {
            $this->spy->structured[] = ['agent' => $agentClass, 'provider' => $provider];

            return $this->inner->generateStructured($agentClass, $prompt, $provider);
        }

        /**
         * @return array{base64: string, mime: string}
         */
        public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
        {
            $this->spy->images[] = $prompt;

            return ['base64' => base64_encode('fake-png-bytes'), 'mime' => 'image/png'];
        }
    });

    return $spy;
}

/**
 * Runs one generation end to end and returns the finished row's payload.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function runPostGeneration(User $admin, array $payload): array
{
    $accepted = test()->actingAs($admin)
        ->postJson('/posts/ai/generate-content', $payload)
        ->assertStatus(202);

    $uuid = (string) $accepted->json('data.uuid');

    return (array) test()->actingAs($admin)
        ->getJson("/posts/ai/generations/{$uuid}")
        ->assertOk()
        ->json('data');
}

it('bills exactly one cover image even when the loop runs every iteration', function (): void {
    $max = PostContentQualityEvaluator::MAX_ITERATIONS;

    GeneratePostContentAgent::fake(array_map(
        static fn (int $i): array => postCostDraft("Draft {$i}"),
        range(1, $max),
    ));

    // Never clears the thresholds, so the loop exhausts all five attempts.
    EvaluatePostContentAgent::fake(array_fill(0, $max, postCostVerdict(['virality_score' => 40])));

    $spy = spyOnPostAiCalls();

    $generation = runPostGeneration(postCostAdmin(), [
        'topic' => 'Cost of the loop',
        'provider' => 'openai',
        'image_mode' => 'full',
    ]);

    expect($generation['status'])->toBe(PostAiGenerationStatus::Completed->value)
        ->and($generation['result']['iterations_required'])->toBe($max)
        ->and($generation['result']['quality_warning'])->toBeTrue();

    $writes = array_filter($spy->structured, static fn (array $c): bool => $c['agent'] === GeneratePostContentAgent::class);
    $judgements = array_filter($spy->structured, static fn (array $c): bool => $c['agent'] === EvaluatePostContentAgent::class);

    // Text is cheap and runs per iteration; artwork is expensive and runs once.
    expect($writes)->toHaveCount($max)
        ->and($judgements)->toHaveCount($max)
        ->and($spy->images)->toHaveCount(1);
});

it('scores every attempt on a different provider from the one that wrote it', function (): void {
    GeneratePostContentAgent::fake([postCostDraft()]);
    EvaluatePostContentAgent::fake([postCostVerdict()]);

    $spy = spyOnPostAiCalls();

    $generation = runPostGeneration(postCostAdmin(), [
        'topic' => 'Independent gate',
        'provider' => 'openai',
        'image_mode' => 'none',
    ]);

    expect($generation['result']['provider'])->toBe('openai')
        ->and($generation['result']['evaluator_provider'])->toBe('anthropic');

    $providerFor = static fn (string $agent): ?string => array_first(array_values(array_map(
        static fn (array $c): ?string => $c['provider'],
        array_filter($spy->structured, static fn (array $c): bool => $c['agent'] === $agent),
    )));

    expect($providerFor(GeneratePostContentAgent::class))->toBe('openai')
        ->and($providerFor(EvaluatePostContentAgent::class))->toBe('anthropic')
        ->and($providerFor(EvaluatePostContentAgent::class))
        ->not->toBe($providerFor(GeneratePostContentAgent::class));
});

it('does not re-run the text loop when only the image mode changes', function (): void {
    // One writer response only: a second write would exhaust the fake and fail,
    // which is exactly the assertion — the cached draft must be reused.
    GeneratePostContentAgent::fake([postCostDraft()]);
    EvaluatePostContentAgent::fake([postCostVerdict(), postCostVerdict()]);

    $spy = spyOnPostAiCalls();
    $admin = postCostAdmin();

    $payload = [
        'topic' => 'Image mode toggling',
        'provider' => 'openai',
    ];

    $first = runPostGeneration($admin, [...$payload, 'image_mode' => 'none']);
    $second = runPostGeneration($admin, [...$payload, 'image_mode' => 'full']);

    expect($first['result']['cover_image_path'])->toBeNull()
        ->and($second['result']['title'])->toBe('Cost Draft');

    $writes = array_filter($spy->structured, static fn (array $c): bool => $c['agent'] === GeneratePostContentAgent::class);

    // The text was written once and reused; only the second run paid for art.
    expect($writes)->toHaveCount(1)
        ->and($spy->images)->toHaveCount(1);
});
