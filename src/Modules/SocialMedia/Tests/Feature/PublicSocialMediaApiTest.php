<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Tests\TestCase;

/**
 * The anonymous `/api/social-media/public` feed. What is asserted here is
 * mostly what must NOT be in the payload: the five quality scores, the
 * research trail, the AI-detection self-report and the provider are internal
 * diagnostics, and leaking them would hand any caller the generation playbook
 * for content the brand presents as its own.
 */
final class PublicSocialMediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_feed_lists_only_published_packages(): void
    {
        $published = SocialMediaContentEloquentModel::factory()->published()->create(['topic' => 'Shipped in public']);
        SocialMediaContentEloquentModel::factory()->ready()->create(['topic' => 'Still in review']);
        SocialMediaContentEloquentModel::factory()->scheduled()->create(['topic' => 'Queued for later']);

        $this->getJson('/api/social-media/public')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $published->uuid])
            ->assertJsonFragment(['topic' => 'Shipped in public'])
            ->assertJsonMissing(['topic' => 'Still in review'])
            ->assertJsonMissing(['topic' => 'Queued for later']);
    }

    public function test_the_public_feed_requires_no_authentication(): void
    {
        SocialMediaContentEloquentModel::factory()->published()->create();

        $this->getJson('/api/social-media/public')->assertOk();
    }

    public function test_the_public_feed_never_leaks_internal_diagnostics(): void
    {
        SocialMediaContentEloquentModel::factory()->published()->create();

        $response = $this->getJson('/api/social-media/public')->assertOk();
        $body = $response->getContent();

        foreach ([
            'human_writing_index',
            'virality_score',
            'engagement_score',
            'roi_score',
            'trend_alignment',
            'overall_score_avg',
            'ai_detection_risk',
            'research_sources',
            'tavily_data_used',
            'quality_warning',
            'iterations_required',
            'cover_image_prompt',
            'provider',
            'created_by',
        ] as $internalField) {
            $this->assertStringNotContainsString($internalField, (string) $body);
        }
    }

    public function test_the_list_omits_the_body_but_the_detail_view_includes_it(): void
    {
        $content = SocialMediaContentEloquentModel::factory()->published()->create([
            'body' => 'The full long-form body copy.',
        ]);

        $this->getJson('/api/social-media/public')
            ->assertOk()
            ->assertJsonPath('data.0.body', null);

        $this->getJson("/api/social-media/public/{$content->uuid}")
            ->assertOk()
            ->assertJsonPath('body', 'The full long-form body copy.');
    }

    public function test_an_unpublished_package_is_indistinguishable_from_a_missing_one(): void
    {
        $draft = SocialMediaContentEloquentModel::factory()->ready()->create();

        $this->getJson("/api/social-media/public/{$draft->uuid}")->assertNotFound();
        $this->getJson('/api/social-media/public/'.fake()->uuid())->assertNotFound();
    }

    public function test_per_page_is_capped(): void
    {
        SocialMediaContentEloquentModel::factory()->count(3)->published()->create();

        $this->getJson('/api/social-media/public?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }
}
