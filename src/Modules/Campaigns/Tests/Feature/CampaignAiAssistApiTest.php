<?php

declare(strict_types=1);

namespace Modules\Campaigns\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Campaigns\Infrastructure\Ai\SuggestCampaignTopicsAgent;
use Modules\SocialMedia\Tests\Feature\SocialMediaAiAssistApiTest;
use Tests\TestCase;

/**
 * Covers the secondary Sanctum-authenticated mirror of the AI-assist
 * endpoints (mobile/external clients) — the primary UI remains the web
 * wizard, tested in {@see CampaignAiAssistTest}. Only the auth/permission
 * wiring is asserted here since the generation logic itself is already
 * covered there — mirrors {@see SocialMediaAiAssistApiTest}.
 */
final class CampaignAiAssistApiTest extends TestCase
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

        SuggestCampaignTopicsAgent::fake([
            [
                'niche_analysis' => [
                    'target_audience' => 'SMB owners',
                    'key_pain_points' => ['slow lead response'],
                    'trending_topics' => ['AI-assisted follow-up'],
                    'tavily_insights' => [],
                ],
                'campaign_topics' => array_map(static fn (int $i): array => [
                    'title' => "Angle {$i}",
                    'angle' => 'angle',
                    'hook' => 'hook',
                    'platform' => 'facebook',
                    'estimated_virality' => 70,
                    'estimated_engagement' => 'high',
                    'estimated_roi' => 65,
                    'estimated_lead_potential' => 68,
                    'difficulty' => 'easy',
                    'why_it_works' => 'timely offer',
                    'key_trend' => 'trend',
                    'suggested_format' => 'lead_form',
                    'content_type' => 'promotional',
                    'funnel_stage' => 'tofu',
                ], range(1, 10)),
            ],
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/campaigns/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'campaigns.ai.topics_suggested',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_api_ai_endpoints_are_gated_by_create_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        Sanctum::actingAs($plain);

        $this->postJson('/api/campaigns/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertForbidden();
    }

    public function test_api_ai_endpoints_reject_unauthenticated_requests(): void
    {
        $this->postJson('/api/campaigns/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertUnauthorized();
    }
}
