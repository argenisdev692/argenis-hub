<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\SocialMedia\Domain\Enums\SocialMediaContentStatus;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Tests\TestCase;

final class PublishScheduledSocialMediaContentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_content_whose_scheduled_at_has_passed(): void
    {
        $due = SocialMediaContentEloquentModel::factory()->create([
            'status' => SocialMediaContentStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);

        $future = SocialMediaContentEloquentModel::factory()->create([
            'status' => SocialMediaContentStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        Artisan::call('social-media:publish-scheduled');

        $due->refresh();
        $future->refresh();

        $this->assertSame('published', $due->status->value);
        $this->assertNotNull($due->published_at);
        $this->assertSame('scheduled', $future->status->value);
        $this->assertNull($future->published_at);
    }

    public function test_it_reports_zero_when_nothing_is_due(): void
    {
        SocialMediaContentEloquentModel::factory()->create([
            'status' => SocialMediaContentStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        $exitCode = Artisan::call('social-media:publish-scheduled');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Published 0 scheduled social media content package(s).', Artisan::output());
    }
}
