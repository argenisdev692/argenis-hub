<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use RuntimeException;

/**
 * {@see DatabaseSeeder} calls this seeder under `WithoutModelEvents`, so the
 * model's `creating` hook — the usual source of `uuid` — never fires. The column
 * is NOT NULL, so a seeder that leans on that hook explodes on a cold database
 * while passing on a warm one (every row already exists, so only UPDATEs run).
 * These tests pin BOTH paths.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
});

it('inserts the categories on a cold database with model events muted', function (): void {
    expect(BlogCategoryEloquentModel::query()->count())->toBe(0);

    Model::withoutEvents(fn () => $this->seed(BlogCategorySeeder::class));

    $categories = BlogCategoryEloquentModel::query()->orderBy('blog_category_name')->get();

    expect($categories)->toHaveCount(4)
        ->and($categories->pluck('blog_category_name')->all())
        ->toBe(['AI', 'Marketing Online', 'Social Network', 'Software']);

    foreach ($categories as $category) {
        expect($category->uuid)->not->toBeNull()
            ->and($category->uuid)->toMatch('/^[0-9a-f-]{36}$/');
    }
});

it('keeps every uuid stable across re-runs', function (): void {
    Model::withoutEvents(fn () => $this->seed(BlogCategorySeeder::class));
    $before = BlogCategoryEloquentModel::query()->pluck('uuid', 'blog_category_name')->all();

    Model::withoutEvents(fn () => $this->seed(BlogCategorySeeder::class));
    $after = BlogCategoryEloquentModel::query()->pluck('uuid', 'blog_category_name')->all();

    expect($after)->toBe($before)
        ->and(BlogCategoryEloquentModel::query()->count())->toBe(4);
});

it('refreshes description, image and owner on a re-run', function (): void {
    $this->seed(BlogCategorySeeder::class);

    BlogCategoryEloquentModel::query()
        ->where('blog_category_name', 'AI')
        ->update(['blog_category_description' => 'drifted', 'blog_category_image' => null]);

    $this->seed(BlogCategorySeeder::class);

    $ai = BlogCategoryEloquentModel::query()->where('blog_category_name', 'AI')->sole();

    expect($ai->blog_category_description)->toBe('Artificial Intelligence trends, tools and insights')
        ->and($ai->blog_category_image)->toContain('blog-categories-cards/ai.webp');
});

it('assigns the seeded SUPER_ADMIN as the owner', function (): void {
    $this->seed(BlogCategorySeeder::class);

    $superAdminId = User::query()->role('SUPER_ADMIN')->orderBy('id')->value('id');

    expect(BlogCategoryEloquentModel::query()->pluck('user_id')->unique()->all())
        ->toBe([$superAdminId]);
});

it('fails loudly when no SUPER_ADMIN exists', function (): void {
    User::query()->forceDelete();

    expect(fn () => $this->seed(BlogCategorySeeder::class))
        ->toThrow(RuntimeException::class);
});
