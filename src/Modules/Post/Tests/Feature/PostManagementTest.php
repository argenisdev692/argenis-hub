<?php

declare(strict_types=1);

namespace Modules\Post\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;
use Tests\TestCase;

final class PostManagementTest extends TestCase
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

    public function test_super_admin_creates_a_draft_post(): void
    {
        $admin = $this->superAdmin();
        $category = BlogCategoryEloquentModel::factory()->create();

        $this->actingAs($admin)
            ->post('/posts', [
                'title' => 'How We Cut Onboarding Time in Half',
                'content' => 'A long-form article body about onboarding improvements.',
                'excerpt' => 'A short teaser.',
                'category_uuid' => $category->uuid,
                'status' => 'draft',
            ])
            ->assertRedirect();

        $post = PostEloquentModel::query()->where('post_title', 'How We Cut Onboarding Time in Half')->firstOrFail();

        $this->assertSame($admin->id, $post->user_id);
        $this->assertSame($category->id, $post->category_id);
        $this->assertSame('draft', $post->post_status->value);
        $this->assertSame('how-we-cut-onboarding-time-in-half', $post->post_title_slug);
        $this->assertNull($post->published_at);
    }

    public function test_publishing_stamps_published_at(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/posts', [
                'title' => 'Publishing Right Away',
                'content' => 'Body content.',
                'status' => 'published',
            ])
            ->assertRedirect();

        $post = PostEloquentModel::query()->where('post_title', 'Publishing Right Away')->firstOrFail();

        $this->assertNotNull($post->published_at);
    }

    public function test_scheduling_without_a_future_date_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/posts', [
                'title' => 'Scheduled Post',
                'content' => 'Body content.',
                'status' => 'scheduled',
            ])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_duplicate_titles_get_a_unique_slug(): void
    {
        PostEloquentModel::factory()->create(['post_title' => 'Same Title', 'post_title_slug' => 'same-title']);

        $this->actingAs($this->superAdmin())
            ->post('/posts', [
                'title' => 'Same Title',
                'content' => 'Body content.',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', ['post_title_slug' => 'same-title-2']);
    }

    public function test_updating_a_post_keeps_its_slug_when_the_title_is_unchanged(): void
    {
        $admin = $this->superAdmin();
        $post = PostEloquentModel::factory()->create(['post_title' => 'Stable Title', 'post_title_slug' => 'stable-title']);

        $this->actingAs($admin)->put("/posts/{$post->uuid}", [
            'title' => 'Stable Title',
            'content' => 'Updated body content.',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertSame('stable-title', $post->refresh()->post_title_slug);
        $this->assertSame('Updated body content.', $post->post_content);
    }

    public function test_delete_then_restore_a_post(): void
    {
        $admin = $this->superAdmin();
        $post = PostEloquentModel::factory()->create();

        $this->actingAs($admin)->delete("/posts/{$post->uuid}")->assertRedirect();
        $this->assertSoftDeleted('posts', ['uuid' => $post->uuid]);

        $this->actingAs($admin)->post("/posts/{$post->uuid}/restore")->assertRedirect();
        $this->assertDatabaseHas('posts', ['uuid' => $post->uuid, 'deleted_at' => null]);
    }

    public function test_bulk_delete_then_restore(): void
    {
        $admin = $this->superAdmin();
        $uuids = PostEloquentModel::factory()->count(3)->create()->pluck('uuid')->all();

        $this->actingAs($admin)->post('/posts/bulk-delete', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertSoftDeleted('posts', ['uuid' => $uuid]);
        }

        $this->actingAs($admin)->post('/posts/bulk-restore', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('posts', ['uuid' => $uuid, 'deleted_at' => null]);
        }
    }

    public function test_bulk_delete_rejects_more_than_500_uuids(): void
    {
        $uuids = array_map(static fn (): string => (string) Str::uuid(), range(1, 501));

        $this->actingAs($this->superAdmin())
            ->postJson('/posts/bulk-delete', ['uuids' => $uuids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uuids');
    }

    public function test_search_filter_narrows_the_list(): void
    {
        PostEloquentModel::factory()->create(['post_title' => 'Artificial Intelligence Trends']);
        PostEloquentModel::factory()->create(['post_title' => 'Gardening Tips']);

        $this->actingAs($this->superAdmin())
            ->getJson('/posts?search=Intelligence')
            ->assertOk()
            ->assertJsonFragment(['post_title' => 'Artificial Intelligence Trends'])
            ->assertJsonMissing(['post_title' => 'Gardening Tips']);
    }

    public function test_absolute_cover_image_url_is_not_double_prefixed(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-current.example.test']);
        Storage::forgetDisk('r2');

        $cover = 'https://pub-legacy.example.test/posts/ai/003a5dd7-eb71-40b0-b81b-993a61c77a4c.png';

        $post = PostEloquentModel::factory()->create([
            'post_title' => 'Legacy Cover Post',
            'post_cover_image' => $cover,
        ]);

        $this->assertSame($cover, $post->cover_image_url);

        $this->actingAs($this->superAdmin())
            ->getJson('/posts')
            ->assertOk()
            ->assertJsonFragment(['cover_image_url' => $cover])
            ->assertJsonMissing(['cover_image_url' => 'https://pub-current.example.test/'.$cover]);
    }

    public function test_object_key_cover_image_resolves_via_configured_r2_url(): void
    {
        config(['filesystems.disks.r2.url' => 'https://pub-current.example.test']);
        Storage::forgetDisk('r2');

        $key = 'posts/ai/003a5dd7-eb71-40b0-b81b-993a61c77a4c.png';
        $post = PostEloquentModel::factory()->create(['post_cover_image' => $key]);

        $this->assertSame('https://pub-current.example.test/'.$key, $post->cover_image_url);
    }

    public function test_create_normalizes_absolute_cover_image_path_to_object_key(): void
    {
        $admin = $this->superAdmin();
        $absolute = 'https://pub-legacy.example.test/posts/ai/003a5dd7-eb71-40b0-b81b-993a61c77a4c.png';

        $this->actingAs($admin)
            ->post('/posts', [
                'title' => 'Cover Path Normalized',
                'content' => 'Body content.',
                'status' => 'draft',
                'cover_image_path' => $absolute,
            ])
            ->assertRedirect();

        $post = PostEloquentModel::query()->where('post_title', 'Cover Path Normalized')->firstOrFail();

        $this->assertSame('posts/ai/003a5dd7-eb71-40b0-b81b-993a61c77a4c.png', $post->post_cover_image);
    }

    public function test_a_user_without_permission_cannot_manage_posts(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)->get('/posts')->assertForbidden();
        $this->actingAs($plain)->post('/posts', ['title' => 'X', 'content' => 'Y', 'status' => 'draft'])->assertForbidden();
    }
}
