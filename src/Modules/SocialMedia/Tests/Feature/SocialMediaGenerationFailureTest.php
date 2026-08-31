<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;
use RuntimeException;
use Tests\TestCase;

/**
 * The quality loop runs with `Tries(1)`, so a worker timeout or crash is
 * terminal. Without a `failed()` handler the row stayed on `generating`
 * forever and the wizard polled for a completion event that could never
 * arrive — these tests pin the cleanup.
 */
final class SocialMediaGenerationFailureTest extends TestCase
{
    use RefreshDatabase;

    private function job(SocialMediaContentEloquentModel $content): GenerateSocialMediaContentJob
    {
        return new GenerateSocialMediaContentJob(
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
    }

    public function test_a_failed_job_releases_the_row_from_the_generating_state(): void
    {
        $content = SocialMediaContentEloquentModel::factory()->create(['status' => 'generating']);

        $this->job($content)->failed(new RuntimeException('worker timed out'));

        $content->refresh();
        $this->assertSame('needs_review', $content->status->value);
        $this->assertTrue($content->quality_warning);
        $this->assertStringContainsString('did not finish', (string) $content->quality_warning_message);
    }

    public function test_a_failed_job_never_overwrites_an_already_completed_package(): void
    {
        $content = SocialMediaContentEloquentModel::factory()->ready()->create();

        $this->job($content)->failed(new RuntimeException('late failure callback'));

        $content->refresh();
        $this->assertSame('ready', $content->status->value);
        $this->assertFalse($content->quality_warning);
    }

    public function test_a_failed_job_for_a_deleted_row_is_a_no_op(): void
    {
        $content = SocialMediaContentEloquentModel::factory()->create(['status' => 'generating']);
        $uuid = $content->uuid;
        $content->forceDelete();

        $this->job(new SocialMediaContentEloquentModel(['uuid' => $uuid, 'topic' => 'gone']))
            ->failed(new RuntimeException('row vanished'));

        $this->assertDatabaseMissing('social_media_contents', ['uuid' => $uuid]);
    }
}
