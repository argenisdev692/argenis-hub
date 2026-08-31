<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Domain\Enums\PostImageMode;
use Modules\Post\Infrastructure\Ai\GeneratePostContentAgent;
use Modules\Post\Infrastructure\Ai\SuggestPostTopicsAgent;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Branding\BrandPalette;

/*
| Category-first topic ideation + the three cover-image render modes.
|
| Tavily is not faked explicitly — TAVILY_API_KEY is unset in the testing
| environment, so TavilyResearchAdapter::search() short-circuits to an empty
| array without any HTTP call. The image provider IS faked, because the whole
| point of the mode switch is how many billed image calls it makes and which
| layer each one draws.
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function postAssistAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * @return array<string, mixed>
 */
function postAssistDraftPayload(): array
{
    return [
        'title' => 'Generated Title',
        'content' => 'Generated body content.',
        'excerpt' => 'Generated excerpt.',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
        'meta_keywords' => 'kw1, kw2',
        'cover_image_concept' => [
            'title' => 'Onboarding Automated',
            'visual' => 'a stylized workflow node network',
        ],
        'scores' => [
            'seo_score' => 80,
            'eeat_score' => 75,
            'virality_score' => 76,
            'roi_score' => 74,
            'human_writing_index' => 82,
            'ai_detection_risk' => 15,
        ],
        'seo_analysis' => [
            'primary_keyword' => 'onboarding',
            'lsi_keywords' => ['kw1', 'kw2'],
        ],
        'optimization_suggestions' => ['Add more data.'],
    ];
}

/**
 * Decorates the bound AI client so structured generation keeps flowing through
 * the agent fakes while every image prompt is recorded instead of billed. The
 * returned spy exposes `->prompts`, so a test can assert both HOW MANY image
 * calls happened and WHICH layer was drawn.
 */
function spyOnPostImageProvider(): object
{
    // The adapter stores rendered bytes through StoragePort → the cloud disk;
    // fake it so no test ever reaches Cloudflare R2.
    Storage::fake((string) config('filesystems.cloud', 'r2'));

    $spy = new class
    {
        /** @var list<string> */
        public array $prompts = [];
    };

    $inner = app(AIClientInterface::class);

    app()->instance(AIClientInterface::class, new class($inner, $spy) implements AIClientInterface
    {
        public function __construct(private AIClientInterface $inner, private object $spy) {}

        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null): StructuredAgentResponse
        {
            return $this->inner->generateStructured($agentClass, $prompt, $provider);
        }

        /**
         * @return array{base64: string, mime: string}
         */
        public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
        {
            $this->spy->prompts[] = $prompt;

            return ['base64' => base64_encode('fake-png-bytes'), 'mime' => 'image/png'];
        }
    });

    return $spy;
}

function fakeTenTopicIdeas(int $virality = 70): void
{
    SuggestPostTopicsAgent::fake([
        [
            'niche_analysis' => [
                'target_audience' => 'SMB owners',
                'trending_topics' => ['AI automation'],
            ],
            'content_ideas' => array_map(static fn (int $i): array => [
                'title' => "Viral {$i}",
                'angle' => 'angle',
                'hook' => 'hook',
                'estimated_virality' => $virality,
                'estimated_roi' => 65,
                'eeat_potential' => 80,
                'why_it_works' => 'because',
                'key_trend' => 'trend',
            ], range(1, 10)),
        ],
    ]);
}

it('requires a category before suggesting topics', function (): void {
    $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/suggest-topics', ['provider' => 'openai'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_uuid');
});

it('rejects a category uuid that does not exist', function (): void {
    $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/suggest-topics', [
            'provider' => 'openai',
            'category_uuid' => (string) Str::uuid7(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_uuid');
});

it('rejects a soft-deleted category', function (): void {
    $category = BlogCategoryEloquentModel::factory()->create();
    $category->delete();

    $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/suggest-topics', [
            'provider' => 'openai',
            'category_uuid' => $category->uuid,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_uuid');
});

it('returns exactly ten viral ideas for the chosen category', function (): void {
    fakeTenTopicIdeas(virality: 85);

    $category = BlogCategoryEloquentModel::factory()->create([
        'blog_category_name' => 'Backend Engineering',
        'blog_category_description' => 'Laravel, APIs and system design.',
    ]);

    $admin = postAssistAdmin();

    $this->actingAs($admin)
        ->postJson('/posts/ai/suggest-topics', [
            'provider' => 'openai',
            'category_uuid' => $category->uuid,
            'topic' => 'serverless',
        ])
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.title', 'Viral 1')
        ->assertJsonPath('data.0.estimated_virality', 85);

    $this->assertDatabaseHas('activity_log', [
        'event' => 'post.ai.topics_suggested',
        'causer_id' => $admin->id,
    ]);
});

it('renders the full composite cover in full image mode', function (): void {
    GeneratePostContentAgent::fake([postAssistDraftPayload()]);
    $spy = spyOnPostImageProvider();

    $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/generate-content', [
            'topic' => 'Onboarding automation',
            'provider' => 'openai',
            'image_mode' => 'full',
        ])
        ->assertOk()
        ->assertJsonPath('data.image_mode', 'full')
        ->assertJsonPath('data.title', 'Generated Title');

    expect($spy->prompts)->toHaveCount(1)
        ->and($spy->prompts[0])->toContain('Premium tech social media graphic')
        ->and($spy->prompts[0])->toContain('Onboarding Automated')
        ->and($spy->prompts[0])->toContain('workflow node network')
        ->and($spy->prompts[0])->toContain(BrandPalette::BACKGROUND);
});

it('renders only the palette background plate in base image mode', function (): void {
    GeneratePostContentAgent::fake([postAssistDraftPayload()]);
    $spy = spyOnPostImageProvider();

    $response = $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/generate-content', [
            'topic' => 'Onboarding automation',
            'provider' => 'openai',
            'image_mode' => 'base',
        ])
        ->assertOk()
        ->assertJsonPath('data.image_mode', 'base');

    expect($spy->prompts)->toHaveCount(1);

    $rendered = $spy->prompts[0];

    // The base render is literally the advertised background prompt, and it
    // carries neither the subject nor the title.
    expect($rendered)->toBe((string) $response->json('data.image_prompts.background'))
        ->and($rendered)->toContain(BrandPalette::BACKGROUND)
        ->and($rendered)->toContain(BrandPalette::PRIMARY_ACCENT)
        ->and($rendered)->toContain(BrandPalette::SECONDARY_ACCENT)
        ->and($rendered)->not->toContain('workflow node network')
        ->and($rendered)->not->toContain('Onboarding Automated');

    expect($response->json('data.cover_image_path'))->not->toBeNull();
});

it('bills no image call in none image mode but still returns both prompts', function (): void {
    GeneratePostContentAgent::fake([postAssistDraftPayload()]);
    $spy = spyOnPostImageProvider();

    $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/generate-content', [
            'topic' => 'Onboarding automation',
            'provider' => 'openai',
            'image_mode' => 'none',
        ])
        ->assertOk()
        ->assertJsonPath('data.image_mode', 'none')
        ->assertJsonPath('data.cover_image_path', null)
        ->assertJsonPath('data.cover_image_url', null)
        ->assertJsonStructure(['data' => ['image_prompts' => ['background', 'content']]]);

    expect($spy->prompts)->toBeEmpty();
});

it('rejects an unknown image mode', function (): void {
    $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/generate-content', [
            'topic' => 'Onboarding automation',
            'provider' => 'openai',
            'image_mode' => 'watercolour',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image_mode');
});

it('defaults to the full composite when no image mode is sent', function (): void {
    GeneratePostContentAgent::fake([postAssistDraftPayload()]);
    $spy = spyOnPostImageProvider();

    $this->actingAs(postAssistAdmin())
        ->postJson('/posts/ai/generate-content', [
            'topic' => 'Defaulted image mode',
            'provider' => 'openai',
        ])
        ->assertOk()
        ->assertJsonPath('data.image_mode', 'full');

    expect($spy->prompts)->toHaveCount(1);
});

it('reports which modes bill an image call', function (): void {
    expect(PostImageMode::Full->rendersImage())->toBeTrue()
        ->and(PostImageMode::Base->rendersImage())->toBeTrue()
        ->and(PostImageMode::None->rendersImage())->toBeFalse();
});
