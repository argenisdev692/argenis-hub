<?php

declare(strict_types=1);

namespace Modules\Campaigns\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Domain\Ports\CampaignAssetRendererPort;
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;
use Modules\Campaigns\Infrastructure\Ai\EvaluateCampaignAgent;
use Modules\Campaigns\Infrastructure\Ai\GenerateCampaignAgent;
use Modules\Campaigns\Infrastructure\Ai\SuggestCampaignTopicsAgent;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Modules\Campaigns\Infrastructure\Queue\GenerateCampaignJob;
use Modules\SocialMedia\Tests\Feature\SocialMediaAiAssistTest;
use Tests\TestCase;

/**
 * Tavily is not faked explicitly — its API key is unset in the testing
 * environment, so the adapter short-circuits (empty research) without any
 * HTTP call, same convention as
 * {@see SocialMediaAiAssistTest}.
 */
final class CampaignAiAssistTest extends TestCase
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
                    'funnel_stage' => $i <= 6 ? 'tofu' : ($i <= 8 ? 'mofu' : ($i === 9 ? 'bofu' : 'loyalty')),
                ], range(1, 10)),
            ],
        ]);

        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson('/campaigns/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.9.funnel_stage', 'loyalty');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'campaigns.ai.topics_suggested',
            'causer_id' => $admin->id,
        ]);
    }

    public function test_generate_campaign_dispatches_the_quality_loop_job(): void
    {
        Queue::fake();

        $this->actingAs($this->superAdmin())
            ->postJson('/campaigns/ai/generate-campaign', [
                'topic' => 'Spring lead-gen push',
                'provider' => 'openai',
                'language' => 'en',
                'business_goal' => 'leads',
                'brand_voice' => 'professional',
                'funnel_stage' => 'bofu',
                'platform' => 'both',
                'ad_format' => 'lead_form',
                'generate_images' => false,
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'generating');

        $this->assertDatabaseHas('campaigns', [
            'topic' => 'Spring lead-gen push',
            'status' => 'generating',
        ]);

        Queue::assertPushed(GenerateCampaignJob::class);
    }

    public function test_quality_loop_job_persists_a_ready_campaign_on_first_pass(): void
    {
        GenerateCampaignAgent::fake([$this->passingAttempt()]);
        EvaluateCampaignAgent::fake([$this->verdict()]);

        $campaign = CampaignEloquentModel::factory()->create(['status' => 'generating']);

        $job = new GenerateCampaignJob($campaign->uuid, $this->generationData($campaign));

        app()->call([$job, 'handle']);

        $campaign->refresh();
        $this->assertSame('ready', $campaign->status->value);
        $this->assertTrue($campaign->all_scores_pass);
        $this->assertSame(1, $campaign->iterations_required);
        $this->assertFalse($campaign->quality_warning);
        $this->assertSame('Generated headline', $campaign->headline);
        $this->assertSame('high', $campaign->success_probability_label);
    }

    public function test_quality_loop_job_persists_video_package_for_reel_format(): void
    {
        GenerateCampaignAgent::fake([$this->passingAttempt(withVideo: true)]);
        EvaluateCampaignAgent::fake([$this->verdict()]);

        $campaign = CampaignEloquentModel::factory()->create(['status' => 'generating', 'ad_format' => 'reel']);

        $job = new GenerateCampaignJob(
            $campaign->uuid,
            GenerateCampaignData::from([
                'topic' => $campaign->topic,
                'provider' => 'openai',
                'language' => 'en',
                'business_goal' => 'awareness',
                'brand_voice' => 'trendy',
                'funnel_stage' => 'tofu',
                'platform' => 'instagram',
                'ad_format' => 'reel',
                'generate_images' => false,
                'city' => 'Madrid',
                'country' => 'Spain',
            ]),
        );

        app()->call([$job, 'handle']);

        $campaign->refresh();
        $this->assertSame('ready', $campaign->status->value);
        $instagram = $campaign->platforms['instagram'] ?? [];
        $this->assertIsArray($instagram['video_package'] ?? null);
        $this->assertSame(15, $instagram['video_package']['target_duration_seconds'] ?? null);
        $this->assertSame('ugc_native', $instagram['video_package']['creative_style'] ?? null);
        $this->assertNotEmpty($instagram['video_package']['scenes'] ?? []);
    }

    /**
     * The reason the pipeline is split into write / judge / render at all: a
     * run that needs every one of its five iterations must still bill the
     * image and speech providers EXACTLY ONCE, for the attempt that won.
     * Rendering inside the loop cost up to 15 images to keep 3.
     */
    public function test_artwork_is_rendered_once_for_the_winner_not_per_iteration(): void
    {
        GenerateCampaignAgent::fake(array_fill(0, CampaignQualityEvaluator::MAX_ITERATIONS, $this->passingAttempt()));
        EvaluateCampaignAgent::fake(array_fill(0, CampaignQualityEvaluator::MAX_ITERATIONS, $this->verdict(passing: false)));

        $renderer = new class implements CampaignAssetRendererPort
        {
            public int $calls = 0;

            public function render(
                string $campaignUuid,
                CampaignDraftData $draft,
                GenerateCampaignData $data,
                ?object $causer = null,
            ): CampaignDraftData {
                $this->calls++;

                return $draft;
            }
        };

        $this->app->instance(CampaignAssetRendererPort::class, $renderer);

        $campaign = CampaignEloquentModel::factory()->create(['status' => 'generating']);

        app()->call([new GenerateCampaignJob($campaign->uuid, $this->generationData($campaign)), 'handle']);

        $campaign->refresh();

        $this->assertSame(CampaignQualityEvaluator::MAX_ITERATIONS, $campaign->iterations_required);
        $this->assertSame('needs_review', $campaign->status->value);
        $this->assertTrue($campaign->quality_warning);
        $this->assertSame(1, $renderer->calls, 'The asset renderer must run once per RUN, never once per iteration.');
    }

    /**
     * The writer no longer grades itself: its schema has no `scores` key at
     * all, so a run in which only the writer is faked cannot produce a passing
     * campaign — the independent judge has to speak.
     */
    public function test_scores_come_from_the_independent_judge_not_the_writer(): void
    {
        GenerateCampaignAgent::fake(array_fill(0, CampaignQualityEvaluator::MAX_ITERATIONS, $this->passingAttempt()));
        EvaluateCampaignAgent::fake(array_fill(0, CampaignQualityEvaluator::MAX_ITERATIONS, $this->verdict(passing: false)));

        $campaign = CampaignEloquentModel::factory()->create(['status' => 'generating']);

        app()->call([new GenerateCampaignJob($campaign->uuid, $this->generationData($campaign)), 'handle']);

        $campaign->refresh();

        $this->assertFalse($campaign->all_scores_pass);
        $this->assertSame(42, $campaign->audience_fit_score);
        $this->assertSame('low', $campaign->success_probability_label);
        $this->assertSame('needs_review', $campaign->status->value);
    }

    public function test_ai_endpoints_are_gated_by_create_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)
            ->postJson('/campaigns/ai/suggest-topics', ['provider' => 'openai', 'language' => 'en'])
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function passingAttempt(bool $withVideo = false): array
    {
        $videoPackage = [
            'scenes' => [
                [
                    'time_range' => '0-3s',
                    'action' => 'Hook cut',
                    'on_screen_text' => 'Stop scrolling',
                    'voiceover_line' => 'Your leads go cold in under an hour.',
                    'visual_prompt' => 'phone-camera UGC close-up, native Reels framing',
                ],
                [
                    'time_range' => '3-10s',
                    'action' => 'Problem',
                    'on_screen_text' => 'Slow follow-up',
                    'voiceover_line' => 'Slow follow-up is killing your pipeline in this market.',
                    'visual_prompt' => 'creator pointing at CRM delay chart overlay',
                ],
                [
                    'time_range' => '10-15s',
                    'action' => 'CTA',
                    'on_screen_text' => 'Learn more',
                    'voiceover_line' => 'Learn the playbook that fixed ours.',
                    'visual_prompt' => 'lo-fi end card with soft CTA text space',
                ],
            ],
            'clean_script' => 'Your leads go cold in under an hour. Slow follow-up is killing your pipeline in this market. Learn the playbook that fixed ours.',
            'sound_suggestion' => 'Punchy tech beat with hard cut on hook — search CapCut for "ad hook whoosh"',
            'target_duration_seconds' => 15,
            'creative_style' => 'ugc_native',
        ];

        $platform = static fn (): array => array_filter([
            'adapted_primary_text' => 'Adapted primary text.',
            'character_count' => 120,
            'headline' => 'Short headline',
            'description' => 'Short description',
            'hashtags' => ['#leads'],
            'image_concept' => ['title' => 'Short Title', 'visual' => 'a glowing lead-form icon'],
            'video_package' => $withVideo ? $videoPackage : null,
        ], static fn ($v): bool => $v !== null);

        return [
            'content' => [
                'headline' => 'Generated headline',
                'primary_text' => 'Generated primary text.',
                'description' => 'Generated description.',
                'call_to_action' => 'GET_QUOTE',
                'hashtags' => ['#leads', '#metaads'],
                'lead_form_questions' => ['What is your budget range?'],
                'targeting_suggestions' => ['Lookalike of existing customers'],
            ],
            'platforms' => [
                'facebook' => $platform(),
                'instagram' => $platform(),
            ],
            'cover_image_concept' => ['title' => 'Short Title', 'visual' => 'a glowing lead-form icon'],
            'research_sources' => [],
            'tavily_data_used' => [],
        ];
    }

    /**
     * The INDEPENDENT judge's verdict — a separate agent on a separate
     * provider. `$passing = false` produces a verdict that fails every
     * threshold, which is how the loop is driven past iteration 1.
     *
     * @return array<string, mixed>
     */
    private function verdict(bool $passing = true): array
    {
        $score = static fn (int $value, array $factors): array => [
            'value' => $value,
            'factors' => $factors,
            'explanation' => 'Lead with the 3-hour response-time stat instead of a benefit claim.',
        ];

        $shift = $passing ? 0 : -40;

        return [
            'scores' => [
                'audience_fit_score' => $score(82 + $shift, ['audience_alignment' => 85, 'niche_specificity' => 78, 'pain_point_accuracy' => 83, 'brand_fit' => 80, 'geographic_relevance' => 84]),
                'virality_score' => $score(76 + $shift, ['hook_strength' => 82, 'shareability' => 74, 'timing' => 71, 'emotional_trigger' => 77]),
                'roi_potential_score' => $score(74 + $shift, ['cta_strength' => 75, 'value_proposition' => 73, 'conversion_potential' => 74]),
                'lead_quality_score' => $score(78 + $shift, ['qualifying_power' => 80, 'targeting_specificity' => 76, 'pre_qualification' => 78, 'form_relevance' => 77]),
                'trend_relevance_score' => $score(79 + $shift, ['current_trend' => 81, 'timeliness' => 77, 'platform_format' => 79]),
            ],
            'optimization_suggestions' => ['Add one more proof point.'],
            'ai_detection_risk' => ['value' => 15, 'label' => 'low', 'explanation' => 'Reads as human-written.'],
        ];
    }

    private function generationData(CampaignEloquentModel $campaign, string $adFormat = 'lead_form', bool $generateImages = false): GenerateCampaignData
    {
        return GenerateCampaignData::from([
            'topic' => $campaign->topic,
            'provider' => 'openai',
            'language' => 'en',
            'business_goal' => 'leads',
            'brand_voice' => 'professional',
            'funnel_stage' => 'bofu',
            'platform' => 'both',
            'ad_format' => $adFormat,
            'generate_images' => $generateImages,
        ]);
    }
}
