<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Post\Tests\Feature\PostAiAssistApiTest;
use Modules\SocialMedia\Infrastructure\Ai\SuggestSocialMediaTopicsAgent;
use Tests\TestCase;

/**
 * Covers the secondary Sanctum-authenticated mirror of the AI-assist
 * endpoints (mobile/external clients) — the primary UI remains the web
 * wizard, tested in {@see SocialMediaAiAssistTest}. Only the auth/permission
 * wiring is asserted here since the generation logic itself is already
 * covered there — mirrors {@see PostAiAssistApiTest}.
 */
final class SocialMediaAiAssistApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_api_suggests_topics_with_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        SuggestSocialMediaTopicsAgent::fake([
            [
                'niche_analysis' => [
                    'target_audience' => 'SMB owners',
                    'key_pain_points' => ['slow onboarding'],
                    'trending_topics' => ['AI automation'],
                    'tavily_insights' => [],
                ],
                'viral_topics' => array_map(static fn (int $i): array => [
                    'title' => "Idea {$i}",
                    'angle' => 'angle',
                    'hook' => 'hook',
                    'platform' => 'linkedin',
                    'estimated_virality' => 70,
                    'estimated_engagement' => 'high',
                    'estimated_roi' => 65,
                    'difficulty' => 'easy',
                    'why_it_works' => 'timely news',
                    'key_trend' => 'trend',
                    'suggested_format' => 'post',
                    'content_type' => 'educational',
                    'funnel_stage' => 'tofu',
                ], range(1, 10)),
            ],
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/social-media/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'social_media.ai.topics_suggested',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_api_ai_endpoints_are_gated_by_create_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        Sanctum::actingAs($plain);

        $this->postJson('/api/social-media/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertForbidden();
    }

    public function test_api_ai_endpoints_reject_unauthenticated_requests(): void
    {
        $this->postJson('/api/social-media/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertUnauthorized();
    }
}
