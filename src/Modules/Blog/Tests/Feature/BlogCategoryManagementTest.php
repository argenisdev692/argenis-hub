<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * SUPER_ADMIN holds every `*_BLOG_CATEGORIES` permission seeded by
 * {@see RolePermissionSeeder}, so it exercises the happy path of every route.
 */
function blogCategoryAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('creates a blog category with an image', function (): void {
    Storage::fake('r2');
    $admin = blogCategoryAdmin();

    $this->actingAs($admin)
        ->post('/blog-categories', [
            'name' => 'DevOps',
            'description' => 'Pipelines, infra and automation',
            'image' => UploadedFile::fake()->image('devops.png', 200, 200),
        ])
        ->assertRedirect();

    $category = BlogCategoryEloquentModel::query()
        ->where('blog_category_name', 'DevOps')
        ->firstOrFail();

    expect($category->user_id)->toBe($admin->id)
        ->and($category->blog_category_image)->not->toBeNull();

    Storage::disk('r2')->assertExists($category->blog_category_image);
});

it('allows creating a blog category without an image', function (): void {
    $this->actingAs(blogCategoryAdmin())
        ->post('/blog-categories', ['name' => 'No Image'])
        ->assertRedirect();

    $this->assertDatabaseHas('blog_categories', [
        'blog_category_name' => 'No Image',
        'blog_category_image' => null,
    ]);
});

it('rejects a duplicate blog category name', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Unique']);

    $this->actingAs(blogCategoryAdmin())
        ->post('/blog-categories', ['name' => 'Unique'])
        ->assertSessionHasErrors('name');
});

it('rejects a non-image upload', function (): void {
    Storage::fake('r2');

    $this->actingAs(blogCategoryAdmin())
        ->post('/blog-categories', [
            'name' => 'Payload',
            'image' => UploadedFile::fake()->create('shell.php', 12, 'application/x-httpd-php'),
        ])
        ->assertSessionHasErrors('image');
});

it('replaces the image on update and deletes the superseded object', function (): void {
    Storage::fake('r2');
    $admin = blogCategoryAdmin();

    $this->actingAs($admin)->post('/blog-categories', [
        'name' => 'Cloud',
        'image' => UploadedFile::fake()->image('old.png', 120, 120),
    ])->assertRedirect();

    $category = BlogCategoryEloquentModel::query()->where('blog_category_name', 'Cloud')->firstOrFail();
    $oldPath = $category->blog_category_image;

    $this->actingAs($admin)->put("/blog-categories/{$category->uuid}", [
        'name' => 'Cloud',
        'image' => UploadedFile::fake()->image('new.png', 120, 120),
    ])->assertRedirect();

    $newPath = $category->refresh()->blog_category_image;

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('r2')->assertMissing($oldPath);
    Storage::disk('r2')->assertExists($newPath);
});

it('keeps the existing image when an update sends no file', function (): void {
    Storage::fake('r2');
    $admin = blogCategoryAdmin();
    $category = BlogCategoryEloquentModel::factory()->withImage()->create();

    $this->actingAs($admin)
        ->put("/blog-categories/{$category->uuid}", ['name' => 'Renamed'])
        ->assertRedirect();

    expect($category->refresh()->blog_category_image)->toBe('blog-categories/example.webp')
        ->and($category->blog_category_name)->toBe('Renamed');
});

it('shows a single blog category', function (): void {
    $category = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Design']);

    $this->actingAs(blogCategoryAdmin())
        ->getJson("/blog-categories/{$category->uuid}")
        ->assertOk()
        ->assertJsonPath('data.blog_category_name', 'Design')
        ->assertJsonMissingPath('data.id');
});

it('rejects a non-uuid route segment', function (): void {
    $this->actingAs(blogCategoryAdmin())
        ->get('/blog-categories/not-a-uuid')
        ->assertNotFound();
});

it('soft-deletes then restores a blog category', function (): void {
    $admin = blogCategoryAdmin();
    $category = BlogCategoryEloquentModel::factory()->create();

    $this->actingAs($admin)->delete("/blog-categories/{$category->uuid}")->assertRedirect();
    $this->assertSoftDeleted('blog_categories', ['uuid' => $category->uuid]);

    $this->actingAs($admin)->post("/blog-categories/{$category->uuid}/restore")->assertRedirect();
    $this->assertDatabaseHas('blog_categories', ['uuid' => $category->uuid, 'deleted_at' => null]);
});

it('bulk-deletes then bulk-restores blog categories', function (): void {
    $admin = blogCategoryAdmin();
    $uuids = BlogCategoryEloquentModel::factory()->count(3)->create()->pluck('uuid')->all();

    $this->actingAs($admin)->post('/blog-categories/bulk-delete', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertSoftDeleted('blog_categories', ['uuid' => $uuid]);
    }

    $this->actingAs($admin)->post('/blog-categories/bulk-restore', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertDatabaseHas('blog_categories', ['uuid' => $uuid, 'deleted_at' => null]);
    }
});

it('caps a bulk payload at 500 uuids', function (): void {
    $uuids = array_map(static fn (): string => (string) Str::uuid7(), range(1, 501));

    $this->actingAs(blogCategoryAdmin())
        ->postJson('/blog-categories/bulk-delete', ['uuids' => $uuids])
        ->assertStatus(422)
        ->assertJsonValidationErrors('uuids');
});

it('narrows the list with the search filter', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Artificial Intelligence']);
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Gardening']);

    $this->actingAs(blogCategoryAdmin())
        ->getJson('/blog-categories?search=Intelligence')
        ->assertOk()
        ->assertJsonFragment(['blog_category_name' => 'Artificial Intelligence'])
        ->assertJsonMissing(['blog_category_name' => 'Gardening']);
});

it('lists only suspended rows when status=suspended', function (): void {
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Live']);
    BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Gone'])->delete();

    $this->actingAs(blogCategoryAdmin())
        ->getJson('/blog-categories?status=suspended')
        ->assertOk()
        ->assertJsonFragment(['blog_category_name' => 'Gone'])
        ->assertJsonMissing(['blog_category_name' => 'Live']);
});

it('rejects a date range whose end precedes its start', function (): void {
    $this->actingAs(blogCategoryAdmin())
        ->getJson('/blog-categories?date_from=2026-05-10&date_to=2026-05-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_from');
});

it('includes both boundaries of an inclusive date range', function (): void {
    $inside = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Boundary']);
    $inside->forceFill(['created_at' => '2026-05-15 23:59:59'])->saveQuietly();

    $outside = BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Outside']);
    $outside->forceFill(['created_at' => '2026-05-16 00:00:01'])->saveQuietly();

    $this->actingAs(blogCategoryAdmin())
        ->getJson('/blog-categories?date_from=2026-05-01&date_to=2026-05-15')
        ->assertOk()
        ->assertJsonFragment(['blog_category_name' => 'Boundary'])
        ->assertJsonMissing(['blog_category_name' => 'Outside']);
});

it('forbids a user without blog-category permissions', function (): void {
    $plain = User::factory()->create();
    $plain->assignRole('USER');

    $this->actingAs($plain)->get('/blog-categories')->assertForbidden();
    $this->actingAs($plain)->post('/blog-categories', ['name' => 'X'])->assertForbidden();
});

it('redirects a guest to login', function (): void {
    $this->get('/blog-categories')->assertRedirect('/login');
});
