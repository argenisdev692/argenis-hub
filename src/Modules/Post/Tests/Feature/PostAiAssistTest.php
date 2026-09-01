<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Infrastructure\Ai\EvaluatePostContentAgent;
use Modules\Post\Infrastructure\Ai\GeneratePostContentAgent;
use Modules\Post\Infrastructure\Ai\SuggestPostTopicsAgent;
use Shared\Infrastructure\Branding\BrandPalette;
use Tests\TestCase;

/**
 * Tavily is not faked explicitly — TAVILY_API_KEY is unset in the testing
 * environment, so TavilyResearchAdapter::search() short-circuits to an empty
 * array without any HTTP call (see its early-return guard).
 */
final class PostAiAssistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    /**
     * Topic ideation is category-first, so every suggest-topics call needs a
     * real category to hang the niche on.
     */
    private function categoryUuid(): string
    {
        return (string) BlogCategoryEloquentModel::factory()->create([
            'blog_category_name' => 'Backend Engineering',
            'blog_category_description' => 'Laravel, APIs and system design.',
        ])->uuid;
    }

    /**
     * The WRITER's output — text only. It no longer scores itself; see
     * {@see self::judgePayload()}.
     *
     * @return array<string, mixed>
     */
    private function draftPayload(string $title = 'Generated Title'): array
    {
        return [
            'title' => $title,
            'content' => 'Generated body content.',
            'excerpt' => 'Generated excerpt.',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
            'meta_keywords' => 'kw1, kw2',
            'cover_image_concept' => [
                'title' => 'Onboarding Automated',
                'visual' => 'a stylized workflow node network',
            ],
            'seo_analysis' => [
                'primary_keyword' => 'onboarding',
                'lsi_keywords' => ['kw1', 'kw2'],
            ],
        ];
    }

    /**
     * The independent JUDGE's verdict. Defaults clear every threshold, so the
     * loop stops on iteration 1; pass overrides to fail specific scores.
     *
     * @param  array<string, int>  $overrides
     * @return array<string, mixed>
     */
    private function judgePayload(array $overrides = []): array
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
                static fn (int $value): array => ['value' => $value, 'explanation' => 'Because it scored that.'],
                $scores,
            ),
            'eeat_analysis' => [
                'experience_signals' => ['One concrete rollout with a date.'],
                'expertise_signals' => ['Explains why the queue backs up.'],
                'authoritativeness_signals' => [],
                'trustworthiness_signals' => ['Names one limitation.'],
            ],
            'ai_detection_risk' => 15,
            'optimization_suggestions' => ['Add more data.'],
        ];
    }

    public function test_suggest_topics_returns_ten_ideas(): void
    {
        SuggestPostTopicsAgent::fake([
            [
                'niche_analysis' => [
                    'target_audience' => 'SMB owners',
                    'trending_topics' => ['AI automation'],
                ],
                'content_ideas' => array_map(static fn (int $i): array => [
                    'title' => "Idea {$i}",
                    'angle' => 'angle',
                    'hook' => 'hook',
                    'estimated_virality' => 70,
                    'estimated_roi' => 65,
                    'eeat_potential' => 80,
                    'why_it_works' => 'because',
                    'key_trend' => 'trend',
                ], range(1, 10)),
            ],
        ]);

        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson('/posts/ai/suggest-topics', [
                'provider' => 'openai',
                'category_uuid' => $this->categoryUuid(),
            ])
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'post.ai.topics_suggested',
            'causer_id' => $admin->id,
        ]);
    }

    /**
     * Runs one generation and returns the finished draft.
     *
     * `generate-content` answers 202 with a queued row now, not the draft —
     * under the suite's `sync` queue driver the job has already finished by the
     * time the response lands, so the poll is one extra call, not a wait. One
     * admin serves both calls: the status endpoint is scoped to whoever started
     * the run.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function generateDraft(array $payload): array
    {
        $admin = $this->superAdmin();

        $uuid = (string) $this->actingAs($admin)
            ->postJson('/posts/ai/generate-content', $payload)
            ->assertStatus(202)
            ->json('data.uuid');

        return (array) $this->actingAs($admin)
            ->getJson("/posts/ai/generations/{$uuid}")
            ->assertOk()
            ->json('data.result');
    }

    public function test_generate_content_returns_a_scored_draft_with_layered_image_prompts(): void
    {
        GeneratePostContentAgent::fake([$this->draftPayload()]);
        EvaluatePostContentAgent::fake([$this->judgePayload()]);

        $draft = $this->generateDraft([
            'topic' => 'Onboarding automation',
            'provider' => 'openai',
            'image_mode' => 'none',
        ]);

        $this->assertSame('Generated Title', $draft['title']);
        $this->assertSame(80, $draft['seo_score']);
        $this->assertSame(76, $draft['virality_score']);
        $this->assertSame(74, $draft['roi_score']);
        $this->assertTrue($draft['all_scores_pass']);
        $this->assertFalse($draft['quality_warning']);
        $this->assertSame(1, $draft['iterations_required']);
        $this->assertNull($draft['cover_image_path']);
        $this->assertArrayHasKey('background', $draft['image_prompts']);
        $this->assertArrayHasKey('content', $draft['image_prompts']);

        $background = (string) $draft['image_prompts']['background'];
        $content = (string) $draft['image_prompts']['content'];

        // Asserted through the constants, never as literals: this test used to
        // pin #0a0a1a / #6366f1 / #a78bfa, which is how BrandPalette drifted
        // away from resources/css/globals.css without anything failing.
        $this->assertStringContainsString(BrandPalette::BACKGROUND, $background);
        $this->assertStringContainsString(BrandPalette::PRIMARY_ACCENT, $background);
        $this->assertStringContainsString(BrandPalette::SECONDARY_ACCENT, $background);
        $this->assertStringContainsString('workflow node network', $content);
        $this->assertStringContainsString('Onboarding Automated', $content);
    }

    public function test_generate_content_quality_loop_keeps_best_attempt_with_warning(): void
    {
        // Exhaust the 5-iteration loop with near-passes so the best attempt is
        // returned with quality_warning rather than regenerating forever. The
        // scores come from the JUDGE, not the writer — the writer only varies
        // the title so the assertion can prove which attempt survived.
        GeneratePostContentAgent::fake([
            $this->draftPayload('Weak Draft'),
            $this->draftPayload('Better Draft'),
            $this->draftPayload('Better Draft'),
            $this->draftPayload('Better Draft'),
            $this->draftPayload('Better Draft'),
        ]);

        $weak = $this->judgePayload([
            'virality_score' => 40,
            'roi_score' => 40,
            'human_writing_index' => 60,
        ]);
        $better = $this->judgePayload([
            'virality_score' => 65,
            'roi_score' => 68,
        ]);

        EvaluatePostContentAgent::fake([$weak, $better, $better, $better, $better]);

        $draft = $this->generateDraft([
            'topic' => 'Quality loop topic',
            'provider' => 'openai',
            'image_mode' => 'none',
        ]);

        $this->assertSame('Better Draft', $draft['title']);
        $this->assertFalse($draft['all_scores_pass']);
        $this->assertTrue($draft['quality_warning']);
        $this->assertSame(5, $draft['iterations_required']);
        $this->assertSame(65, $draft['virality_score']);
    }

    public function test_ai_endpoints_are_gated_by_create_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)
            ->postJson('/posts/ai/suggest-topics', ['provider' => 'openai'])
            ->assertForbidden();
    }
}
