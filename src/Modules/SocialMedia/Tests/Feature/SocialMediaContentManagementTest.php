<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;
use Tests\TestCase;

final class SocialMediaContentManagementTest extends TestCase
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

    public function test_super_admin_edits_a_generated_content_package(): void
    {
        $admin = $this->superAdmin();
        $content = SocialMediaContentEloquentModel::factory()->ready()->create();

        $this->actingAs($admin)
            ->put("/social-media/{$content->uuid}", [
                'headline' => 'Updated headline',
                'body' => 'Updated body content.',
                'call_to_action' => 'Book a call today.',
                'hashtags' => ['#tech', '#ai'],
                'status' => 'ready',
            ])
            ->assertRedirect();

        $content->refresh();
        $this->assertSame('Updated headline', $content->headline);
        $this->assertSame('Updated body content.', $content->body);
    }

    public function test_publishing_stamps_published_at(): void
    {
        $admin = $this->superAdmin();
        $content = SocialMediaContentEloquentModel::factory()->ready()->create();

        $this->actingAs($admin)->post("/social-media/{$content->uuid}/publish")->assertRedirect();

        $content->refresh();
        $this->assertSame('published', $content->status->value);
        $this->assertNotNull($content->published_at);
    }

    public function test_scheduling_without_a_future_date_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $content = SocialMediaContentEloquentModel::factory()->ready()->create();

        $this->actingAs($admin)
            ->put("/social-media/{$content->uuid}", [
                'headline' => 'Headline',
                'body' => 'Body',
                'call_to_action' => 'CTA',
                'hashtags' => [],
                'status' => 'scheduled',
            ])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_delete_then_restore_a_content_package(): void
    {
        $admin = $this->superAdmin();
        $content = SocialMediaContentEloquentModel::factory()->create();

        $this->actingAs($admin)->delete("/social-media/{$content->uuid}")->assertRedirect();
        $this->assertSoftDeleted('social_media_contents', ['uuid' => $content->uuid]);

        $this->actingAs($admin)->post("/social-media/{$content->uuid}/restore")->assertRedirect();
        $this->assertDatabaseHas('social_media_contents', ['uuid' => $content->uuid, 'deleted_at' => null]);
    }

    public function test_bulk_delete_then_restore(): void
    {
        $admin = $this->superAdmin();
        $uuids = SocialMediaContentEloquentModel::factory()->count(3)->create()->pluck('uuid')->all();

        $this->actingAs($admin)->post('/social-media/bulk-delete', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertSoftDeleted('social_media_contents', ['uuid' => $uuid]);
        }

        $this->actingAs($admin)->post('/social-media/bulk-restore', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('social_media_contents', ['uuid' => $uuid, 'deleted_at' => null]);
        }
    }

    public function test_bulk_delete_rejects_more_than_500_uuids(): void
    {
        $uuids = array_map(static fn (): string => (string) Str::uuid(), range(1, 501));

        $this->actingAs($this->superAdmin())
            ->postJson('/social-media/bulk-delete', ['uuids' => $uuids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uuids');
    }

    public function test_search_filter_narrows_the_list(): void
    {
        SocialMediaContentEloquentModel::factory()->create(['topic' => 'Laravel 13 Release Notes']);
        SocialMediaContentEloquentModel::factory()->create(['topic' => 'Gardening Tips']);

        $this->actingAs($this->superAdmin())
            ->getJson('/social-media?search=Laravel')
            ->assertOk()
            ->assertJsonFragment(['topic' => 'Laravel 13 Release Notes'])
            ->assertJsonMissing(['topic' => 'Gardening Tips']);
    }

    public function test_a_user_without_permission_cannot_manage_content(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');
        $content = SocialMediaContentEloquentModel::factory()->create();

        $this->actingAs($plain)->get('/social-media')->assertForbidden();
        $this->actingAs($plain)->post("/social-media/{$content->uuid}/publish")->assertForbidden();
    }
}
