<?php

declare(strict_types=1);

namespace Modules\Availability\Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;
use Tests\TestCase;

final class AvailabilityExceptionManagementTest extends TestCase
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

    public function test_super_admin_creates_a_closure_exception(): void
    {
        $date = now()->addMonths(2)->format('Y-m-d');

        $this->actingAs($this->superAdmin())
            ->post('/availability-exceptions', [
                'date' => $date,
                'is_available' => false,
                'reason' => 'Christmas',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('availability_exceptions', [
            'date' => $date,
            'is_available' => false,
            'start_time' => null,
        ]);
    }

    public function test_forced_open_exception_requires_hours(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/availability-exceptions', [
                'date' => now()->addMonths(2)->format('Y-m-d'),
                'is_available' => true,
            ])
            ->assertSessionHasErrors(['start_time', 'end_time']);
    }

    public function test_forced_open_exception_stores_its_hours(): void
    {
        $date = now()->addMonths(2)->format('Y-m-d');

        $this->actingAs($this->superAdmin())
            ->post('/availability-exceptions', [
                'date' => $date,
                'is_available' => true,
                'start_time' => '10:00',
                'end_time' => '14:00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('availability_exceptions', [
            'date' => $date,
            'is_available' => true,
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
        ]);
    }

    public function test_switching_to_a_closure_clears_the_hours(): void
    {
        $exception = AvailabilityExceptionEloquentModel::factory()->on('2026-05-20')->open('09:00', '12:00')->create();

        $this->actingAs($this->superAdmin())
            ->put("/availability-exceptions/{$exception->uuid}", [
                'date' => '2026-05-20',
                'is_available' => false,
                'reason' => 'Closed after all',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('availability_exceptions', [
            'uuid' => $exception->uuid,
            'is_available' => false,
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    public function test_only_one_active_exception_per_date(): void
    {
        $date = now()->addMonths(2)->format('Y-m-d');
        AvailabilityExceptionEloquentModel::factory()->on($date)->create();

        $this->actingAs($this->superAdmin())
            ->post('/availability-exceptions', [
                'date' => $date,
                'is_available' => false,
                'reason' => 'Duplicate',
            ])
            ->assertSessionHasErrors('date');
    }

    public function test_a_date_can_be_reused_after_the_previous_exception_is_soft_deleted(): void
    {
        $admin = $this->superAdmin();
        $date = now()->addMonths(2)->format('Y-m-d');
        $exception = AvailabilityExceptionEloquentModel::factory()->on($date)->create();

        $this->actingAs($admin)->delete("/availability-exceptions/{$exception->uuid}")->assertRedirect();

        $this->actingAs($admin)
            ->post('/availability-exceptions', [
                'date' => $date,
                'is_available' => false,
                'reason' => 'Recreated',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_cannot_create_an_exception_in_the_past(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/availability-exceptions', [
                'date' => now()->subDay()->format('Y-m-d'),
                'is_available' => false,
                'reason' => 'Yesterday',
            ])
            ->assertSessionHasErrors('date');
    }

    public function test_a_past_exception_can_still_be_edited(): void
    {
        $date = now()->subMonth()->format('Y-m-d');
        $exception = AvailabilityExceptionEloquentModel::factory()->on($date)->create();

        $this->actingAs($this->superAdmin())
            ->put("/availability-exceptions/{$exception->uuid}", [
                'date' => $date,
                'is_available' => false,
                'reason' => 'Updated reason',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_bulk_delete_then_restore(): void
    {
        $admin = $this->superAdmin();
        $uuids = AvailabilityExceptionEloquentModel::factory()->count(3)->create()->pluck('uuid')->all();

        $this->actingAs($admin)->post('/availability-exceptions/bulk-delete', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertSoftDeleted('availability_exceptions', ['uuid' => $uuid]);
        }

        $this->actingAs($admin)->post('/availability-exceptions/bulk-restore', ['uuids' => $uuids])->assertRedirect();
        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('availability_exceptions', ['uuid' => $uuid, 'deleted_at' => null]);
        }
    }

    public function test_super_admin_can_export_exceptions_as_csv(): void
    {
        AvailabilityExceptionEloquentModel::factory()->on('2026-05-20')->create();

        $this->actingAs($this->superAdmin())
            ->get('/availability-exceptions/export?format=csv')
            ->assertOk();
    }

    public function test_a_user_without_permission_cannot_export_exceptions(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)->get('/availability-exceptions/export?format=csv')->assertForbidden();
    }

    public function test_a_user_without_permission_cannot_manage_exceptions(): void
    {
        $plain = User::factory()->create();
        $plain->assignRole('USER');

        $this->actingAs($plain)->get('/availability-exceptions')->assertForbidden();
        $this->actingAs($plain)->post('/availability-exceptions', [
            'date' => '2026-01-01',
            'is_available' => false,
        ])->assertForbidden();
    }
}
