<?php

declare(strict_types=1);

namespace Modules\Blog\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Tests\TestCase;

final class BlogCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        return $admin;
    }

    public function test_super_admin_creates_a_blog_category_with_an_image(): void
    {
        Storage::fake('r2');
        $admin = $this->superAdmin();

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

        $this->assertSame($admin->id, $category->user_id);
        $this->assertNotNull($category->blog_category_image);
        Storage::disk('r2')->assertExists($category->blog_category_image);
    }

    public function test_create_without_an_image_is_allowed(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/blog-categories', ['name' => 'No Image'])
            ->assertRedirect();

        $this->assertDatabaseHas('blog_categories', [
            'blog_category_name' => 'No Image',
            'blog_category_image' => null,
        ]);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Unique']);

        $this->actingAs($this->superAdmin())
            ->post('/blog-categories', ['name' => 'Unique'])
            ->assertSessionHasErrors('name');
    }

    public function test_update_replaces_the_image_and_deletes_the_previous_object(): void
    {
        Storage::fake('r2');
        $admin = $this->superAdmin();

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

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('r2')->assertMissing($oldPath);
        Storage::disk('r2')->assertExists($newPath);
    }

    public function test_delete_then_restore_a_blog_category(): void
    {
        $admin = $this->superAdmin();
        $category = BlogCategoryEloquentModel::factory()->create();

        $this->actingAs($admin)->delete("/blog-categories/{$category->uuid}")->assertRedirect();
        $this->assertSoftDeleted('blog_categories', ['uuid' => $category->uuid]);

        $this->actingAs($admin)->post("/blog-categories/{$category->uuid}/restore")->assertRedirect();
        $this->assertDatabaseHas('blog_categories', ['uuid' => $category->uuid, 'deleted_at' => null]);
    }

    public function test_bulk_delete_then_restore(): void
    {
        $admin = $this->superAdmin();
        $uuids = BlogCategoryEloquentModel::factory()->count(3)->create()->pluck('uuid')->all();

        $this->actingAs($admin)->post('/blog-categories/bulk-delete', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertSoftDeleted('blog_categories', ['uuid' => $uuid]);
        }

        $this->actingAs($admin)->post('/blog-categories/bulk-restore', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('blog_categories', ['uuid' => $uuid, 'deleted_at' => null]);
        }
    }

    public function test_bulk_delete_rejects_more_than_500_uuids(): void
    {
        $uuids = array_map(static fn (): string => (string) Str::uuid(), range(1, 501));

        $this->actingAs($this->superAdmin())
            ->postJson('/blog-categories/bulk-delete', ['uuids' => $uuids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uuids');
    }

    public function test_search_filter_narrows_the_list(): void
    {
        BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Artificial Intelligence']);
        BlogCategoryEloquentModel::factory()->create(['blog_category_name' => 'Gardening']);

        $this->actingAs($this->superAdmin())
            ->getJson('/blog-categories?search=Intelligence')
            ->assertOk()
            ->assertJsonFragment(['blog_category_name' => 'Artificial Intelligence'])
            ->assertJsonMissing(['blog_category_name' => 'Gardening']);
    }

    public function test_a_user_without_permission_cannot_manage_blog_categories(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)->get('/blog-categories')->assertForbidden();
        $this->actingAs($plain)->post('/blog-categories', ['name' => 'X'])->assertForbidden();
    }
}
