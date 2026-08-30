<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Post\Domain\Enums\PostStatus;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Tests\TestCase;

final class PublishScheduledPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_posts_whose_scheduled_at_has_passed(): void
    {
        $due = PostEloquentModel::factory()->create([
            'post_status' => PostStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);

        $future = PostEloquentModel::factory()->create([
            'post_status' => PostStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        $draft = PostEloquentModel::factory()->create([
            'post_status' => PostStatus::Draft,
        ]);

        Artisan::call('posts:publish-scheduled');

        $due->refresh();
        $future->refresh();
        $draft->refresh();

        $this->assertSame('published', $due->post_status->value);
        $this->assertNotNull($due->published_at);
        $this->assertSame('scheduled', $future->post_status->value);
        $this->assertNull($future->published_at);
        $this->assertSame('draft', $draft->post_status->value);
    }

    public function test_it_reports_zero_when_nothing_is_due(): void
    {
        PostEloquentModel::factory()->create([
            'post_status' => PostStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);

        $exitCode = Artisan::call('posts:publish-scheduled');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Published 0 scheduled post(s).', Artisan::output());
    }
}
