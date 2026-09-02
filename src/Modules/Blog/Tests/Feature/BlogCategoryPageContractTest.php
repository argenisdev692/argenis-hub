<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;

/**
 * The contract the `blog-categories` Vue module consumes.
 *
 * `BlogCategoryController::index()` and `show()` branch on `expectsJson()`, so
 * one route serves two consumers that nothing else pins together:
 *
 * - the Inertia branch names the page component (`resources/js/pages/blog-categories/`)
 *   and hands `Show.vue` its `blogCategory` prop;
 * - the JSON branch is what the Pinia Colada query in
 *   `modules/blog-categories/composables/useBlogCategories.ts` reads, and its
 *   row shape is the Eloquent serialization the hand-written `BlogCategory`
 *   type in `modules/blog-categories/types.ts` mirrors — there is no `Data`
 *   class for `typescript:transform` to generate it from.
 *
 * A renamed component, a dropped `select()` column or a lost `$appends` would
 * otherwise only surface as an `undefined` in the busiest column of the admin
 * table. These tests fail instead.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function blogCategoryPageAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('renders the index page component the Vue module provides', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Robotics']);

    $this->actingAs(blogCategoryPageAdmin())
        ->get('/blog-categories')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('blog-categories/Index')
            ->has('blogCategories')
            ->has('filters'));
});

it('renders the show page component with the prop Show.vue reads', function (): void {
    $category = BlogCategoryEloquentModel::factory()->create([
        'blog_category_name' => 'Robotics',
        'blog_category_description' => 'Actuators and control loops',
    ]);

    $this->actingAs(blogCategoryPageAdmin())
        ->get("/blog-categories/{$category->uuid}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('blog-categories/Show')
            ->where('blogCategory.uuid', $category->uuid)
            ->where('blogCategory.blog_category_name', 'Robotics')
            ->where('blogCategory.blog_category_description', 'Actuators and control loops')
            ->has('blogCategory.image_url')
            ->has('blogCategory.created_at')
            ->has('blogCategory.updated_at')
            ->has('blogCategory.deleted_at'));
});

it('serves the admin table exactly the row shape its TypeScript type mirrors', function (): void {
    $author = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    BlogCategoryEloquentModel::factory()->forUser($author)->create(['blog_category_name' => 'Robotics']);

    $this->actingAs(blogCategoryPageAdmin())
        ->getJson('/blog-categories')
        ->assertOk()
        // The flat paginator shape `BlogCategoryPage` declares — NOT Inertia's
        // nested `meta` block, because `index()` calls `response()->json()`.
        ->assertJsonStructure([
            'data' => [[
                'uuid',
                'blog_category_name',
                'blog_category_description',
                'blog_category_image',
                'image_url',
                'user_id',
                'created_at',
                'deleted_at',
                'user' => ['first_name', 'last_name'],
            ]],
            'current_page',
            'last_page',
            'per_page',
            'from',
            'to',
            'total',
        ])
        // `$hidden = ['id']` keeps the primary key off the wire; the type says so.
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonPath('data.0.user.first_name', 'Ada');
});

it('never mixes active and suspended rows, which is why the UI offers no "all" status', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Live']);
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Gone'])->delete();

    // `status=active` and an omitted status both leave the SoftDeletes global
    // scope in place, so the trashed row is unreachable from either. The status
    // select in `Index.vue` exposes only Active/Suspended for this reason.
    $this->actingAs(blogCategoryPageAdmin())
        ->getJson('/blog-categories?status=active')
        ->assertOk()
        ->assertJsonFragment(['blog_category_name' => 'Live'])
        ->assertJsonMissing(['blog_category_name' => 'Gone']);
});

it('honours the per_page the table sends', function (): void {
    BlogCategoryEloquentModel::factory()->count(3)->create();

    $this->actingAs(blogCategoryPageAdmin())
        ->getJson('/blog-categories?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('per_page', 2)
        ->assertJsonPath('current_page', 2)
        ->assertJsonCount(1, 'data');
});
