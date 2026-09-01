<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Blog\Infrastructure\Cache\BlogCategoryPublicFeedCache;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;

beforeEach(function (): void {
    Cache::flush();
    BlogCategoryPublicFeedCache::flushStatically();
});

it('serves the public feed without authentication', function (): void {
    BlogCategoryEloquentModel::factory()->create();

    $this->getJson('/api/blog-categories/public')->assertOk();
});

it('does not expose internal ids on the public feed', function (): void {
    BlogCategoryEloquentModel::factory()->create();

    $this->getJson('/api/blog-categories/public')
        ->assertOk()
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.user_id');
});

it('counts only published posts', function (): void {
    $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Engineering']);
    PostEloquentModel::factory()->published()->create(['category_id' => $category->id]);
    PostEloquentModel::factory()->create(['category_id' => $category->id]); // draft

    // Posts are created after the beforeEach flush — bust again so a warm empty
    // feed from another code path cannot hide the new published row.
    BlogCategoryPublicFeedCache::flushStatically();

    $row = collect($this->getJson('/api/blog-categories/public')->assertOk()->json('data'))
        ->firstWhere('name', 'Engineering');

    expect($row)->not->toBeNull()
        ->and($row['posts_count'])->toBe(1);
});

it('embeds published posts as a json relationship', function (): void {
    $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Engineering']);
    PostEloquentModel::factory()->published()->create([
        'category_id' => $category->id,
        'post_title' => 'Scaling Postgres',
    ]);
    PostEloquentModel::factory()->create(['category_id' => $category->id]); // draft — must be excluded

    BlogCategoryPublicFeedCache::flushStatically();

    $row = collect($this->getJson('/api/blog-categories/public')->assertOk()->json('data'))
        ->firstWhere('name', 'Engineering');

    expect($row['posts'])->toBeArray()->toHaveCount(1)
        ->and($row['posts'][0]['title'])->toBe('Scaling Postgres')
        ->and($row['posts'][0])->toHaveKeys(['slug', 'published_at'])
        ->and($row['posts'][0])->not->toHaveKey('id')
        ->and($row['posts'][0])->not->toHaveKey('content');
});

it('bounds the embedded posts per category while reporting the true count', function (): void {
    $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Engineering']);
    PostEloquentModel::factory()->published()->count(15)->create(['category_id' => $category->id]);

    BlogCategoryPublicFeedCache::flushStatically();

    $row = collect($this->getJson('/api/blog-categories/public')->assertOk()->json('data'))
        ->firstWhere('name', 'Engineering');

    // OWASP API4 — the payload is capped at 12 embedded posts, but `posts_count`
    // still reports every published post so the UI can offer "view all".
    expect($row['posts'])->toHaveCount(12)
        ->and($row['posts_count'])->toBe(15);
});

it('excludes suspended categories', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Deleted Soon'])->delete();

    $this->getJson('/api/blog-categories/public')
        ->assertOk()
        ->assertJsonMissing(['name' => 'Deleted Soon']);
});

it('returns absolute image urls when the column already stores a full url', function (): void {
    $url = 'https://cdn.example.test/blog-categories-cards/ai.webp';

    BlogCategoryEloquentModel::factory()->create([
        'blog_category_name' => 'AI',
        'blog_category_image' => $url,
    ]);

    $this->getJson('/api/blog-categories/public')
        ->assertOk()
        ->assertJsonPath('data.0.image_url', $url);
});
