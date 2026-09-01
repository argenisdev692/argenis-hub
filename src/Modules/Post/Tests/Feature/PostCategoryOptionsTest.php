<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;

/**
 * The post form's Category `<select>` is fed by the `categories` Inertia prop
 * that `PostController::categoryOptions()` builds from the Blog module's
 * categories. Nothing else proves that wiring, so these tests pin the contract
 * the Vue side consumes: `{ value: uuid, label: name }`, sorted by name.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function postFormAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('feeds the create form with every blog category as a value/label option', function (): void {
    $ai = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'AI']);
    $software = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Software']);

    $this->actingAs(postFormAdmin())
        ->get('/posts/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('posts/Create')
            ->has('categories', 2)
            ->where('categories.0', ['value' => $ai->uuid, 'label' => 'AI'])
            ->where('categories.1', ['value' => $software->uuid, 'label' => 'Software']));
});

it('sorts the options by category name', function (): void {
    foreach (['Software', 'AI', 'Marketing Online'] as $name) {
        BlogCategoryEloquentModel::factory()->create(['blog_category_name' => $name]);
    }

    $this->actingAs(postFormAdmin())
        ->get('/posts/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('categories.0.label', 'AI')
            ->where('categories.1.label', 'Marketing Online')
            ->where('categories.2.label', 'Software'));
});

it('feeds the edit form with the same options', function (): void {
    $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Design']);
    $post = PostEloquentModel::factory()->create(['category_id' => $category->id]);

    $this->actingAs(postFormAdmin())
        ->get("/posts/{$post->uuid}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('posts/Edit')
            ->where('categories.0', ['value' => $category->uuid, 'label' => 'Design']));
});

it('omits suspended categories from the options', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Live']);
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Retired'])->delete();

    $this->actingAs(postFormAdmin())
        ->get('/posts/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories', 1)
            ->where('categories.0.label', 'Live'));
});

it('never leaks the internal category id to the form', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'AI']);

    $this->actingAs(postFormAdmin())
        ->get('/posts/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('categories.0.id')
            ->missing('categories.0.user_id'));
});

it('renders the form with an empty option list when no categories exist', function (): void {
    $this->actingAs(postFormAdmin())
        ->get('/posts/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('categories', 0));
});
