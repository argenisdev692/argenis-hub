<?php

declare(strict_types=1);

namespace Modules\Availability\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;
use Tests\TestCase;

final class AvailabilityRuleManagementTest extends TestCase
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

    public function test_super_admin_creates_a_rule(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/availability-rules', [
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'is_available' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('availability_rules', [
            'day_of_week' => 1,
            'is_available' => true,
        ]);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/availability-rules', [
                'day_of_week' => 1,
                'start_time' => '13:00',
                'end_time' => '09:00',
                'is_available' => true,
            ])
            ->assertSessionHasErrors('end_time');
    }

    public function test_overlapping_available_slots_on_the_same_day_are_rejected(): void
    {
        AvailabilityRuleEloquentModel::factory()->forDay(2)->slot('09:00', '13:00')->create();

        $this->actingAs($this->superAdmin())
            ->post('/availability-rules', [
                'day_of_week' => 2,
                'start_time' => '12:00',
                'end_time' => '15:00',
                'is_available' => true,
            ])
            ->assertSessionHasErrors('start_time');
    }

    public function test_a_non_overlapping_split_shift_is_allowed(): void
    {
        AvailabilityRuleEloquentModel::factory()->forDay(2)->slot('09:00', '13:00')->create();

        $this->actingAs($this->superAdmin())
            ->post('/availability-rules', [
                'day_of_week' => 2,
                'start_time' => '14:00',
                'end_time' => '18:00',
                'is_available' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, AvailabilityRuleEloquentModel::where('day_of_week', 2)->count());
    }

    public function test_update_a_rule(): void
    {
        $rule = AvailabilityRuleEloquentModel::factory()->forDay(3)->slot('09:00', '13:00')->create();

        $this->actingAs($this->superAdmin())
            ->put("/availability-rules/{$rule->uuid}", [
                'day_of_week' => 3,
                'start_time' => '10:00',
                'end_time' => '12:00',
                'is_available' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('availability_rules', [
            'uuid' => $rule->uuid,
            'start_time' => '10:00:00',
            'is_available' => false,
        ]);
    }

    public function test_delete_then_restore_a_rule(): void
    {
        $admin = $this->superAdmin();
        $rule = AvailabilityRuleEloquentModel::factory()->create();

        $this->actingAs($admin)->delete("/availability-rules/{$rule->uuid}")->assertRedirect();
        $this->assertSoftDeleted('availability_rules', ['uuid' => $rule->uuid]);

        $this->actingAs($admin)->post("/availability-rules/{$rule->uuid}/restore")->assertRedirect();
        $this->assertDatabaseHas('availability_rules', ['uuid' => $rule->uuid, 'deleted_at' => null]);
    }

    public function test_bulk_delete_then_restore(): void
    {
        $admin = $this->superAdmin();
        $uuids = AvailabilityRuleEloquentModel::factory()->count(3)->create()->pluck('uuid')->all();

        $this->actingAs($admin)->post('/availability-rules/bulk-delete', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertSoftDeleted('availability_rules', ['uuid' => $uuid]);
        }

        $this->actingAs($admin)->post('/availability-rules/bulk-restore', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('availability_rules', ['uuid' => $uuid, 'deleted_at' => null]);
        }
    }

    public function test_bulk_delete_rejects_more_than_500_uuids(): void
    {
        $uuids = array_map(static fn (): string => (string) Str::uuid(), range(1, 501));

        $this->actingAs($this->superAdmin())
            ->postJson('/availability-rules/bulk-delete', ['uuids' => $uuids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('uuids');
    }

    /**
     * Asserted over the JSON branch on purpose. The Inertia branch renders
     * `availability-rules/Index`, and that page does not exist under
     * `resources/js/pages/` yet — the frontend module is still to be built — so
     * an HTML assertion here would fail on a missing Vite chunk rather than on
     * anything this controller owns. Switch to `->get()` once the Vue page ships.
     */
    public function test_admin_role_can_manage_rules(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');

        $this->actingAs($admin)->getJson('/availability-rules')->assertOk();
    }

    public function test_super_admin_can_export_rules_as_csv(): void
    {
        AvailabilityRuleEloquentModel::factory()->forDay(1)->slot('09:00', '13:00')->create();

        $this->actingAs($this->superAdmin())
            ->get('/availability-rules/export?format=csv')
            ->assertOk();
    }

    public function test_a_user_without_permission_cannot_export_rules(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)->get('/availability-rules/export?format=csv')->assertForbidden();
    }

    public function test_a_user_without_permission_cannot_manage_rules(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)->get('/availability-rules')->assertForbidden();
        $this->actingAs($plain)->post('/availability-rules', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_available' => true,
        ])->assertForbidden();
    }
}
