<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Post\Infrastructure\Ai\GenerateReelPackageAgent;
use Modules\Post\Infrastructure\Ai\GenerateSocialCopyAgent;
use Tests\TestCase;

/**
 * Tavily and ElevenLabs are not faked explicitly — TAVILY_API_KEY and
 * ELEVENLABS_API_KEY are unset in the testing environment, so both adapters
 * short-circuit to an empty/null result without any HTTP call (see their
 * early-return guards), matching {@see PostAiAssistTest}'s existing pattern.
 */
final class PostSocialAndReelAssistTest extends TestCase
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

    public function test_generate_social_copy_returns_linkedin_and_social_caption(): void
    {
        GenerateSocialCopyAgent::fake([
            [
                'linkedin_post' => 'A LinkedIn post.',
                'social_caption' => 'A short IG/FB caption.',
                'hashtags' => ['#laravel', '#backend', '#php', '#saas', '#vidula'],
            ],
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/posts/ai/generate-social-copy', [
                'topic' => 'Onboarding automation',
                'provider' => 'openai',
            ])
            ->assertOk()
            ->assertJsonPath('data.linkedin_post', 'A LinkedIn post.')
            ->assertJsonPath('data.social_caption', 'A short IG/FB caption.')
            ->assertJsonCount(5, 'data.hashtags');
    }

    public function test_generate_reel_returns_a_scored_package_without_a_voiceover(): void
    {
        GenerateReelPackageAgent::fake([
            [
                'scenes' => [
                    [
                        'time_range' => '0-3s',
                        'action' => 'zoom-in',
                        'on_screen_text' => 'Stop doing this',
                        'voiceover_line' => 'If you are still doing this, stop.',
                        'visual_prompt' => 'code editor close-up',
                    ],
                ],
                'clean_script' => 'If you are still doing this, stop. Here is why.',
                'sound_suggestion' => 'Minimal tech beat, hard cut on the hook.',
                'target_duration_seconds' => 15,
                'creative_style' => 'ugc_native',
                'tiktok_caption' => 'This changes everything.',
                'tiktok_hashtags' => ['#techtok', '#fyp', '#laravel', '#devtips', '#programacion'],
            ],
        ]);

        $this->actingAs($this->superAdmin())
            ->postJson('/posts/ai/generate-reel', [
                'topic' => 'Onboarding automation',
                'provider' => 'openai',
            ])
            ->assertOk()
            ->assertJsonPath('data.clean_script', 'If you are still doing this, stop. Here is why.')
            ->assertJsonPath('data.target_duration_seconds', 15)
            ->assertJsonPath('data.creative_style', 'ugc_native')
            ->assertJsonPath('data.voiceover_audio_url', null)
            ->assertJsonCount(1, 'data.scenes');
    }

    public function test_social_and_reel_endpoints_are_gated_by_create_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)
            ->postJson('/posts/ai/generate-social-copy', ['topic' => 'x', 'provider' => 'openai'])
            ->assertForbidden();

        $this->actingAs($plain)
            ->postJson('/posts/ai/generate-reel', ['topic' => 'x', 'provider' => 'openai'])
            ->assertForbidden();
    }
}
