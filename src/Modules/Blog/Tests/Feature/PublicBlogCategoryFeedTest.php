<?php

declare(strict_types=1);

namespace Modules\Blog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Infrastructure\Cache\BlogCategoryPublicFeedCache;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Tests\TestCase;

final class PublicBlogCategoryFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        BlogCategoryPublicFeedCache::flushStatically();
    }

    public function test_public_feed_requires_no_authentication(): void
    {
        BlogCategoryEloquentModel::factory()->create();

        $this->getJson('/api/blog-categories/public')->assertOk();
    }

    public function test_public_feed_does_not_expose_internal_ids(): void
    {
        BlogCategoryEloquentModel::factory()->create();

        $response = $this->getJson('/api/blog-categories/public')->assertOk();

        $response->assertJsonMissingPath('data.0.id');
        $response->assertJsonMissingPath('data.0.user_id');
    }

    public function test_public_feed_counts_only_published_posts(): void
    {
        $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Engineering']);
        PostEloquentModel::factory()->published()->create(['category_id' => $category->id]);
        PostEloquentModel::factory()->create(['category_id' => $category->id]); // draft

        // Posts are created after setUp cache flush — bust again so a warm empty
        // feed from another code path cannot hide the new published row.
        BlogCategoryPublicFeedCache::flushStatically();

        $response = $this->getJson('/api/blog-categories/public')->assertOk();

        $data = $response->json('data');
        $row = collect($data)->firstWhere('name', 'Engineering');
        $this->assertNotNull($row);
        $this->assertSame(1, $row['posts_count']);
    }

    public function test_public_feed_embeds_published_posts_as_a_json_relationship(): void
    {
        $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Engineering']);
        PostEloquentModel::factory()->published()->create([
            'category_id' => $category->id,
            'post_title' => 'Scaling Postgres',
        ]);
        PostEloquentModel::factory()->create(['category_id' => $category->id]); // draft — must be excluded

        BlogCategoryPublicFeedCache::flushStatically();

        $row = collect($this->getJson('/api/blog-categories/public')->assertOk()->json('data'))
            ->firstWhere('name', 'Engineering');

        $this->assertIsArray($row['posts']);
        $this->assertCount(1, $row['posts']);
        $this->assertSame('Scaling Postgres', $row['posts'][0]['title']);
        $this->assertArrayHasKey('slug', $row['posts'][0]);
        $this->assertArrayHasKey('published_at', $row['posts'][0]);
        $this->assertArrayNotHasKey('id', $row['posts'][0]);
        $this->assertArrayNotHasKey('content', $row['posts'][0]);
    }

    public function test_suspended_categories_are_excluded(): void
    {
        BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Deleted Soon'])->delete();

        $this->getJson('/api/blog-categories/public')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Deleted Soon']);
    }

    public function test_public_feed_returns_absolute_image_urls_when_stored_as_full_url(): void
    {
        $url = 'https://cdn.example.test/blog-categories-cards/ai.webp';

        BlogCategoryEloquentModel::factory()->create([
            'blog_category_name' => 'AI',
            'blog_category_image' => $url,
        ]);

        $this->getJson('/api/blog-categories/public')
            ->assertOk()
            ->assertJsonPath('data.0.image_url', $url);
    }
}
