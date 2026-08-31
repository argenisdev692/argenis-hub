<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Post\Tests\Feature\PostAiAssistTest;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Infrastructure\Ai\EvaluateSocialMediaContentAgent;
use Modules\SocialMedia\Infrastructure\Ai\GenerateSocialMediaContentAgent;
use Modules\SocialMedia\Infrastructure\Ai\SuggestSocialMediaTopicsAgent;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;
use Tests\TestCase;

/**
 * Tavily and ElevenLabs are not faked explicitly — their API keys are unset in
 * the testing environment, so both adapters short-circuit (empty research /
 * null audio) without any HTTP call, same convention as
 * {@see PostAiAssistTest}.
 */
final class SocialMediaAiAssistTest extends TestCase
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

    public function test_suggest_topics_returns_ten_ideas_classified_by_funnel_stage(): void
    {
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
                    'funnel_stage' => $i <= 6 ? 'tofu' : ($i <= 9 ? 'mofu' : 'bofu'),
                ], range(1, 10)),
            ],
        ]);

        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson('/social-media/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.9.funnel_stage', 'bofu');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'social_media.ai.topics_suggested',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_generate_content_dispatches_the_quality_loop_job(): void
    {
        Queue::fake();

        $this->actingAs($this->superAdmin())
            ->postJson('/social-media/ai/generate-content', [
                'topic' => 'Laravel 13 release',
                'provider' => 'openai',
                'language' => 'en',
                'business_goal' => 'awareness',
                'brand_voice' => 'professional',
                'funnel_stage' => 'tofu',
                'image_mode' => 'none',
                'generate_voiceover' => false,
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'generating');

        $this->assertDatabaseHas('social_media_contents', [
            'topic' => 'Laravel 13 release',
            'status' => 'generating',
        ]);

        Queue::assertPushed(GenerateSocialMediaContentJob::class);
    }

    public function test_quality_loop_job_persists_a_ready_package_on_first_pass(): void
    {
        GenerateSocialMediaContentAgent::fake([$this->passingDraft()]);
        EvaluateSocialMediaContentAgent::fake([$this->passingVerdict()]);

        $content = SocialMediaContentEloquentModel::factory()->create(['status' => 'generating']);

        $job = new GenerateSocialMediaContentJob(
            $content->uuid,
            GenerateSocialMediaContentData::from([
                'topic' => $content->topic,
                'provider' => 'openai',
                'language' => 'en',
                'business_goal' => 'awareness',
                'brand_voice' => 'professional',
                'funnel_stage' => 'tofu',
                'image_mode' => 'none',
                'generate_voiceover' => false,
            ]),
        );

        app()->call([$job, 'handle']);

        $content->refresh();
        $this->assertSame('ready', $content->status->value);
        $this->assertTrue($content->all_scores_pass);
        $this->assertSame(1, $content->iterations_required);
        $this->assertFalse($content->quality_warning);
        $this->assertSame('Generated headline', $content->headline);
        $this->assertIsArray($content->platforms['tiktok']['video_package'] ?? null);
        $this->assertNotEmpty($content->platforms['tiktok']['video_package']['scenes'] ?? []);
        $this->assertIsArray($content->platforms['instagram']['video_package'] ?? null);
        $this->assertSame('a', $content->platforms['tiktok']['image_concept']['route'] ?? null);
    }

    public function test_ai_endpoints_are_gated_by_create_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)
            ->postJson('/social-media/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function passingDraft(): array
    {
        $imageConcept = static fn (string $route = 'a'): array => [
            'title' => 'Short Title',
            'visual' => 'a glowing node network',
            'route' => $route,
            'svg_steps' => $route === 'c' ? ['Discover', 'Build', 'Ship'] : [],
        ];

        $videoPackage = [
            'scenes' => [
                [
                    'time_range' => '0-3s',
                    'action' => 'Zoom on emblem',
                    'on_screen_text' => 'Stop doing this',
                    'voiceover_line' => 'If you still ship like this, pause.',
                    'visual_prompt' => 'close-up glowing API gateway icon, phone-camera UGC style',
                ],
                [
                    'time_range' => '3-7s',
                    'action' => 'Cut to pain',
                    'on_screen_text' => 'Latency killing deals',
                    'voiceover_line' => 'Your onboarding latency is killing closes.',
                    'visual_prompt' => 'red latency graph spike on dark UI, native reel framing',
                ],
                [
                    'time_range' => '7-12s',
                    'action' => 'Show payoff',
                    'on_screen_text' => 'Queue + cache win',
                    'voiceover_line' => 'Here is the queue and cache pattern that cut ours in half.',
                    'visual_prompt' => 'animated queue nodes with cache layer, creator-style overlay',
                ],
                [
                    'time_range' => '12-15s',
                    'action' => 'CTA',
                    'on_screen_text' => 'Follow for more',
                    'voiceover_line' => 'Follow for the full playbook.',
                    'visual_prompt' => 'end card with brand mark, lo-fi UGC finish',
                ],
            ],
            'clean_script' => 'If you still ship like this, pause. Your onboarding latency is killing closes. Here is the queue and cache pattern that cut ours in half. Follow for the full playbook.',
            'sound_suggestion' => 'Minimal tech beat with a hard cut on the hook — search CapCut for "tech whoosh beat"',
            'target_duration_seconds' => 15,
            'creative_style' => 'ugc_native',
        ];

        $platform = static fn (bool $withVideo = false, string $route = 'a'): array => array_filter([
            'adapted_content' => 'Adapted content.',
            'character_count' => 120,
            'is_thread' => false,
            'thread_tweets' => [],
            'hashtags' => ['#tech'],
            'image_concept' => $imageConcept($route),
            'video_package' => $withVideo ? $videoPackage : null,
        ], static fn ($v): bool => $v !== null);

        return [
            'content' => [
                'headline' => 'Generated headline',
                'body' => 'Generated body.',
                'call_to_action' => 'Follow for more.',
                'hashtags' => ['#tech', '#laravel'],
            ],
            'platforms' => [
                'linkedin' => $platform(),
                'twitter' => $platform(),
                'instagram' => $platform(withVideo: true, route: 'b'),
                'facebook' => $platform(),
                'tiktok' => $platform(withVideo: true, route: 'a'),
            ],
            'cover_image_concept' => $imageConcept('a'),
            'research_sources' => [],
            'tavily_data_used' => [],
        ];
    }

    /**
     * The judge's verdict. A separate fake because it is a separate model on a
     * separate provider — the writer no longer reports scores at all.
     *
     * @return array<string, mixed>
     */
    private function passingVerdict(): array
    {
        $score = static fn (int $value, array $factors): array => [
            'value' => $value,
            'factors' => $factors,
            'explanation' => 'Clears the bar.',
        ];

        return [
            'scores' => [
                'human_writing_index' => $score(82, ['natural_language' => 85, 'personal_anecdotes' => 78, 'varied_structure' => 83, 'emotional_depth' => 80]),
                'virality_score' => $score(76, ['hook_strength' => 82, 'shareability' => 74, 'timing' => 71, 'emotional_trigger' => 77]),
                'engagement_score' => $score(78, ['cta_strength' => 80, 'interaction_prompt' => 76, 'value_density' => 78, 'emotional_connection' => 77]),
                'roi_score' => $score(74, ['conversion_potential' => 75, 'brand_alignment' => 73, 'lead_generation' => 74]),
                'trend_alignment' => $score(79, ['current_trend' => 81, 'timeliness' => 77, 'platform_format' => 79]),
            ],
            'eeat_analysis' => [
                'experience_signals' => ['first-hand scenario'],
                'expertise_signals' => ['domain terms'],
                'authoritativeness_signals' => ['cites research'],
                'trustworthiness_signals' => ['acknowledges a limitation'],
            ],
            'ai_detection_risk' => ['value' => 15, 'label' => 'low', 'explanation' => 'Reads as human-written.'],
            'optimization_suggestions' => ['Add one more data point.'],
        ];
    }
}
