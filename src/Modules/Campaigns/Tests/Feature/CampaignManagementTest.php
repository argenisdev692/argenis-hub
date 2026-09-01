<?php

declare(strict_types=1);

namespace Modules\Campaigns\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Tests\TestCase;

final class CampaignManagementTest extends TestCase
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

    public function test_super_admin_edits_a_generated_campaign(): void
    {
        $admin = $this->superAdmin();
        $campaign = CampaignEloquentModel::factory()->ready()->create();

        $this->actingAs($admin)
            ->put("/campaigns/{$campaign->uuid}", [
                'headline' => 'Updated headline',
                'primary_text' => 'Updated primary text.',
                'description' => 'Updated description.',
                'call_to_action' => 'GET_QUOTE',
                'hashtags' => ['#leads', '#ads'],
                'lead_form_questions' => ['What is your timeline?'],
                'status' => 'ready',
            ])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('Updated headline', $campaign->headline);
        $this->assertSame('Updated primary text.', $campaign->primary_text);
    }

    public function test_publishing_stamps_published_at(): void
    {
        $admin = $this->superAdmin();
        $campaign = CampaignEloquentModel::factory()->ready()->create();

        $this->actingAs($admin)->post("/campaigns/{$campaign->uuid}/publish")->assertRedirect();

        $campaign->refresh();
        $this->assertSame('published', $campaign->status->value);
        $this->assertNotNull($campaign->published_at);
    }

    public function test_scheduling_without_a_future_date_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $campaign = CampaignEloquentModel::factory()->ready()->create();

        $this->actingAs($admin)
            ->put("/campaigns/{$campaign->uuid}", [
                'headline' => 'Headline',
                'primary_text' => 'Body',
                'description' => null,
                'call_to_action' => 'GET_QUOTE',
                'hashtags' => [],
                'lead_form_questions' => [],
                'status' => 'scheduled',
            ])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_delete_then_restore_a_campaign(): void
    {
        $admin = $this->superAdmin();
        $campaign = CampaignEloquentModel::factory()->create();

        $this->actingAs($admin)->delete("/campaigns/{$campaign->uuid}")->assertRedirect();
        $this->assertSoftDeleted('campaigns', ['uuid' => $campaign->uuid]);

        $this->actingAs($admin)->post("/campaigns/{$campaign->uuid}/restore")->assertRedirect();
        $this->assertDatabaseHas('campaigns', ['uuid' => $campaign->uuid, 'deleted_at' => null]);
    }

    public function test_bulk_delete_then_restore(): void
    {
        $admin = $this->superAdmin();
        $uuids = CampaignEloquentModel::factory()->count(3)->create()->pluck('uuid')->all();

        $this->actingAs($admin)->post('/campaigns/bulk-delete', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertSoftDeleted('campaigns', ['uuid' => $uuid]);
        }

        $this->actingAs($admin)->post('/campaigns/bulk-restore', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('campaigns', ['uuid' => $uuid, 'deleted_at' => null]);
        }
    }

    public function test_bulk_delete_rejects_more_than_500_uuids(): void
    {
        $uuids = array_map(static fn (): string => (string) Str::uuid(), range(1, 501));

        $this->actingAs($this->superAdmin())
            ->postJson('/campaigns/bulk-delete', ['uuids' => $uuids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uuids');
    }

    public function test_search_filter_narrows_the_list(): void
    {
        CampaignEloquentModel::factory()->create(['topic' => 'Spring Roof Inspection Leads']);
        CampaignEloquentModel::factory()->create(['topic' => 'Gardening Tips']);

        $this->actingAs($this->superAdmin())
            ->getJson('/campaigns?search=Roof')
            ->assertOk()
            ->assertJsonFragment(['topic' => 'Spring Roof Inspection Leads'])
            ->assertJsonMissing(['topic' => 'Gardening Tips']);
    }

    public function test_a_user_without_permission_cannot_manage_campaigns(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');
        $campaign = CampaignEloquentModel::factory()->create();

        $this->actingAs($plain)->get('/campaigns')->assertForbidden();
        $this->actingAs($plain)->post("/campaigns/{$campaign->uuid}/publish")->assertForbidden();
    }

    public function test_downloads_a_pdf_report_for_a_generated_campaign(): void
    {
        $admin = $this->superAdmin();
        $campaign = CampaignEloquentModel::factory()->ready()->create();

        $response = $this->actingAs($admin)->get("/campaigns/{$campaign->uuid}/report");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_report_download_is_gated_by_view_permission(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');
        $campaign = CampaignEloquentModel::factory()->create();

        $this->actingAs($plain)->get("/campaigns/{$campaign->uuid}/report")->assertForbidden();
    }
}
