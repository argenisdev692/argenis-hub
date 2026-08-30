<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Tests\TestCase;

final class PublicPostFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->app->make(PostPublicFeedCachePort::class)->flush();
    }

    public function test_public_feed_survives_a_second_cached_hit(): void
    {
        PostEloquentModel::factory()->published()->create(['post_title' => 'Cached Post']);

        $this->getJson('/api/posts/public')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Cached Post']);

        $this->getJson('/api/posts/public')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Cached Post'])
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_public_detail_survives_a_second_cached_hit(): void
    {
        $post = PostEloquentModel::factory()->published()->create([
            'post_title' => 'Cached Detail',
            'post_content' => 'Body for the detail cache.',
        ]);

        $this->getJson("/api/posts/public/{$post->post_title_slug}")
            ->assertOk()
            ->assertJsonPath('content', 'Body for the detail cache.');

        $this->getJson("/api/posts/public/{$post->post_title_slug}")
            ->assertOk()
            ->assertJsonPath('title', 'Cached Detail')
            ->assertJsonPath('content', 'Body for the detail cache.')
            ->assertJsonMissingPath('id');
    }

    public function test_public_feed_only_returns_published_posts(): void
    {
        PostEloquentModel::factory()->published()->create(['post_title' => 'Live Post']);
        PostEloquentModel::factory()->create(['post_title' => 'Draft Post']); // draft

        $this->getJson('/api/posts/public')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Live Post'])
            ->assertJsonMissing(['title' => 'Draft Post']);
    }

    public function test_public_feed_excludes_soft_deleted_posts(): void
    {
        PostEloquentModel::factory()->published()->create(['post_title' => 'Gone Soon'])->delete();

        $this->getJson('/api/posts/public')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Gone Soon']);
    }

    public function test_public_feed_omits_content_but_detail_includes_it(): void
    {
        $post = PostEloquentModel::factory()->published()->create([
            'post_title' => 'Full Article',
            'post_content' => 'The full body of the article.',
        ]);

        $this->getJson('/api/posts/public')
            ->assertOk()
            ->assertJsonPath('data.0.content', null);

        $this->getJson("/api/posts/public/{$post->post_title_slug}")
            ->assertOk()
            ->assertJsonPath('content', 'The full body of the article.');
    }

    public function test_public_feed_filters_by_category(): void
    {
        $engineering = BlogCategoryEloquentModel::factory()->create();
        $marketing = BlogCategoryEloquentModel::factory()->create();
        PostEloquentModel::factory()->published()->create(['post_title' => 'Eng Post', 'category_id' => $engineering->id]);
        PostEloquentModel::factory()->published()->create(['post_title' => 'Mkt Post', 'category_id' => $marketing->id]);

        $this->getJson("/api/posts/public?category_uuid={$engineering->uuid}")
            ->assertOk()
            ->assertJsonFragment(['title' => 'Eng Post'])
            ->assertJsonMissing(['title' => 'Mkt Post']);
    }

    public function test_detail_404s_for_a_draft_or_missing_slug(): void
    {
        PostEloquentModel::factory()->create(['post_title_slug' => 'draft-only']); // draft

        $this->getJson('/api/posts/public/draft-only')->assertNotFound();
        $this->getJson('/api/posts/public/does-not-exist')->assertNotFound();
    }

    public function test_detail_does_not_expose_internal_ids(): void
    {
        $post = PostEloquentModel::factory()->published()->create();

        $this->getJson("/api/posts/public/{$post->post_title_slug}")
            ->assertOk()
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('user_id')
            ->assertJsonMissingPath('category_id');
    }

    public function test_publishing_a_post_busts_the_cached_feed(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        // Warm the cache with an empty feed.
        $this->getJson('/api/posts/public')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($admin)->post('/posts', [
            'title' => 'Freshly Published',
            'content' => 'Body content.',
            'status' => 'published',
        ])->assertRedirect();

        $this->getJson('/api/posts/public')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Freshly Published']);
    }

    public function test_public_feed_does_not_double_prefix_absolute_cover_urls(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-current.example.test']);
        Storage::forgetDisk('r2');

        $cover = 'https://pub-legacy.example.test/posts/ai/003a5dd7-eb71-40b0-b81b-993a61c77a4c.png';

        PostEloquentModel::factory()->published()->create([
            'post_title' => 'Legacy Absolute Cover',
            'post_cover_image' => $cover,
        ]);

        $this->getJson('/api/posts/public')
            ->assertOk()
            ->assertJsonFragment(['cover_image_url' => $cover])
            ->assertJsonMissing(['cover_image_url' => 'https://pub-current.example.test/'.$cover]);
    }
}
