<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Post\Infrastructure\Ai\SuggestPostTopicsAgent;
use Tests\TestCase;

/**
 * Covers the secondary Sanctum-authenticated mirror of the AI-assist
 * endpoints (mobile clients) — the primary UI remains the web panel, tested
 * in {@see PostAiAssistTest}. Only the auth/permission wiring is asserted
 * here since the generation logic itself is already covered there.
 */
final class PostAiAssistApiTest extends TestCase
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

        Sanctum::actingAs($admin);

        $this->postJson('/api/posts/ai/suggest-topics', ['provider' => 'openai'])
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'post.ai.topics_suggested',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_api_ai_endpoints_are_gated_by_create_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        Sanctum::actingAs($plain);

        $this->postJson('/api/posts/ai/suggest-topics', ['provider' => 'openai'])
            ->assertForbidden();
    }

    public function test_api_ai_endpoints_reject_unauthenticated_requests(): void
    {
        $this->postJson('/api/posts/ai/suggest-topics', ['provider' => 'openai'])
            ->assertUnauthorized();
    }
}
