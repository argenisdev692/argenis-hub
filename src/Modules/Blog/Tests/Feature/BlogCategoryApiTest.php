<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function blogCategoryApiActor(array $permissions = []): User
{
    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    Sanctum::actingAs($user);

    return $user;
}

it('rejects an unauthenticated api request', function (): void {
    $this->getJson('/api/blog-categories')->assertUnauthorized();
});

it('lists blog categories for a holder of VIEW_ANY_BLOG_CATEGORIES', function (): void {
    blogCategoryApiActor(['VIEW_ANY_BLOG_CATEGORIES']);
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Infrastructure']);

    $this->getJson('/api/blog-categories')
        ->assertOk()
        ->assertJsonFragment(['blog_category_name' => 'Infrastructure']);
});

it('forbids listing without VIEW_ANY_BLOG_CATEGORIES', function (): void {
    blogCategoryApiActor();

    $this->getJson('/api/blog-categories')->assertForbidden();
});

it('shows a blog category for a holder of VIEW_BLOG_CATEGORIES', function (): void {
    blogCategoryApiActor(['VIEW_BLOG_CATEGORIES']);
    $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Security']);

    $this->getJson("/api/blog-categories/{$category->uuid}")
        ->assertOk()
        ->assertJsonPath('data.blog_category_name', 'Security')
        ->assertJsonMissingPath('data.id');
});

it('forbids showing without VIEW_BLOG_CATEGORIES', function (): void {
    blogCategoryApiActor(['VIEW_ANY_BLOG_CATEGORIES']);
    $category = BlogCategoryEloquentModel::factory()->create();

    $this->getJson("/api/blog-categories/{$category->uuid}")->assertForbidden();
});

it('caps the api page size at 100', function (): void {
    blogCategoryApiActor(['VIEW_ANY_BLOG_CATEGORIES']);
    BlogCategoryEloquentModel::factory()->count(3)->create();

    $this->getJson('/api/blog-categories?per_page=5000')
        ->assertOk()
        ->assertJsonPath('per_page', 100);
});

it('rejects a non-uuid api route segment', function (): void {
    blogCategoryApiActor(['VIEW_BLOG_CATEGORIES']);

    $this->getJson('/api/blog-categories/not-a-uuid')->assertNotFound();
});
