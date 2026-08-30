<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Post\Infrastructure\Ai\GeneratePostContentAgent;
use Modules\Post\Infrastructure\Ai\SuggestPostTopicsAgent;
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
     * @return array<string, mixed>
     */
    private function passingDraftPayload(string $title = 'Generated Title'): array
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
            ->postJson('/posts/ai/suggest-topics', ['provider' => 'openai'])
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'post.ai.topics_suggested',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_generate_content_returns_a_scored_draft_with_layered_image_prompts(): void
    {
        GeneratePostContentAgent::fake([
            $this->passingDraftPayload(),
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->postJson('/posts/ai/generate-content', [
                'topic' => 'Onboarding automation',
                'provider' => 'openai',
                'generate_cover_image' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Generated Title')
            ->assertJsonPath('data.seo_score', 80)
            ->assertJsonPath('data.virality_score', 76)
            ->assertJsonPath('data.roi_score', 74)
            ->assertJsonPath('data.all_scores_pass', true)
            ->assertJsonPath('data.quality_warning', false)
            ->assertJsonPath('data.iterations_required', 1)
            ->assertJsonPath('data.cover_image_path', null)
            ->assertJsonStructure([
                'data' => [
                    'image_prompts' => ['background', 'content'],
                ],
            ]);

        $background = (string) $response->json('data.image_prompts.background');
        $content = (string) $response->json('data.image_prompts.content');

        $this->assertStringContainsString('#0a0a1a', $background);
        $this->assertStringContainsString('#6366f1', $background);
        $this->assertStringContainsString('#a78bfa', $background);
        $this->assertStringContainsString('workflow node network', $content);
        $this->assertStringContainsString('Onboarding Automated', $content);
    }

    public function test_generate_content_quality_loop_keeps_best_attempt_with_warning(): void
    {
        $failing = $this->passingDraftPayload('Weak Draft');
        $failing['scores']['virality_score'] = 40;
        $failing['scores']['roi_score'] = 40;
        $failing['scores']['human_writing_index'] = 60;

        $stillFailing = $this->passingDraftPayload('Better Draft');
        $stillFailing['scores']['virality_score'] = 65;
        $stillFailing['scores']['roi_score'] = 68;

        // Exhaust the 5-iteration loop with near-passes so the best attempt
        // is returned with quality_warning rather than regenerating forever.
        GeneratePostContentAgent::fake([
            $failing,
            $stillFailing,
            $stillFailing,
            $stillFailing,
            $stillFailing,
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/posts/ai/generate-content', [
                'topic' => 'Quality loop topic',
                'provider' => 'openai',
                'generate_cover_image' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Better Draft')
            ->assertJsonPath('data.all_scores_pass', false)
            ->assertJsonPath('data.quality_warning', true)
            ->assertJsonPath('data.iterations_required', 5)
            ->assertJsonPath('data.virality_score', 65);
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
