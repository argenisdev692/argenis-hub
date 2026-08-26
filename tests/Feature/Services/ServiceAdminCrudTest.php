<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * `/data/admin/services` — the JSON CRUD surface a future admin table
 * consumes. Every write is guarded by its own `*_SERVICES` permission
 * (seeded ahead of this module in `RolePermissionSeeder::NO_EXPORT_MODULES`).
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function serviceOperator(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function servicePayload(array $overrides = []): array
{
    return [
        'name' => 'Landing Page',
        'slug' => 'landing_page',
        'description' => 'Single-page site optimized for one conversion goal.',
        'is_active' => true,
        'sort_order' => 0,
        ...$overrides,
    ];
}

describe('authorization', function (): void {
    it('turns a guest away from every route', function (): void {
        $service = ServiceEloquentModel::factory()->create();

        $this->getJson(route('services.admin.index'))->assertUnauthorized();
        $this->getJson(route('services.admin.show', $service->uuid))->assertUnauthorized();
        $this->postJson(route('services.admin.store'), servicePayload())->assertUnauthorized();
        $this->putJson(route('services.admin.update', $service->uuid), servicePayload())->assertUnauthorized();
        $this->deleteJson(route('services.admin.destroy', $service->uuid))->assertUnauthorized();
        $this->patchJson(route('services.admin.restore', $service->uuid))->assertUnauthorized();
        $this->postJson(route('services.admin.bulk-delete'), ['uuids' => [$service->uuid]])->assertUnauthorized();
        $this->postJson(route('services.admin.bulk-restore'), ['uuids' => [$service->uuid]])->assertUnauthorized();
    });

    it('refuses a signed-in user who holds no services permission', function (): void {
        $this->actingAs(serviceOperator([]))
            ->getJson(route('services.admin.index'))
            ->assertForbidden();
    });

    it('refuses a reader on the write routes', function (): void {
        $service = ServiceEloquentModel::factory()->create();

        $this->actingAs(serviceOperator(['VIEW_ANY_SERVICES', 'VIEW_SERVICES']))
            ->putJson(route('services.admin.update', $service->uuid), servicePayload())
            ->assertForbidden();
    });
});

describe('listing', function (): void {
    it('paginates active and soft-deleted services together by default', function (): void {
        ServiceEloquentModel::factory()->count(2)->create();
        ServiceEloquentModel::factory()->create()->delete();

        $this->actingAs(serviceOperator(['VIEW_ANY_SERVICES']))
            ->getJson(route('services.admin.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('serializes the paginator flat, not nested under a "meta" key', function (): void {
        ServiceEloquentModel::factory()->create();

        $this->actingAs(serviceOperator(['VIEW_ANY_SERVICES']))
            ->getJson(route('services.admin.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'from',
                'to',
                'total',
            ])
            ->assertJsonMissingPath('meta');
    });

    it('filters by search term', function (): void {
        ServiceEloquentModel::factory()->create(['name' => 'Business Website', 'slug' => 'business_website']);
        ServiceEloquentModel::factory()->create(['name' => 'E-Commerce', 'slug' => 'ecommerce']);

        $this->actingAs(serviceOperator(['VIEW_ANY_SERVICES']))
            ->getJson(route('services.admin.index', ['search' => 'commerce']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'ecommerce');
    });

    it('never exposes the auto-increment key or the owner column', function (): void {
        ServiceEloquentModel::factory()->create();

        $response = $this->actingAs(serviceOperator(['VIEW_ANY_SERVICES']))
            ->getJson(route('services.admin.index'))
            ->assertOk();

        expect($response->json('data.0'))
            ->not->toHaveKey('id')
            ->not->toHaveKey('user_id');
    });
});

describe('creating', function (): void {
    it('creates a service owned by the acting operator', function (): void {
        $operator = serviceOperator(['CREATE_SERVICES']);

        $this->actingAs($operator)
            ->postJson(route('services.admin.store'), servicePayload())
            ->assertCreated()
            ->assertJsonPath('slug', 'landing_page');

        $service = ServiceEloquentModel::query()->where('slug', 'landing_page')->firstOrFail();
        expect($service->user_id)->toBe($operator->id);
    });

    it('rejects a duplicate slug', function (): void {
        ServiceEloquentModel::factory()->create(['slug' => 'landing_page']);

        $this->actingAs(serviceOperator(['CREATE_SERVICES']))
            ->postJson(route('services.admin.store'), servicePayload(['slug' => 'landing_page']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    });

    it('rejects invalid input', function (array $overrides, string $field): void {
        $this->actingAs(serviceOperator(['CREATE_SERVICES']))
            ->postJson(route('services.admin.store'), servicePayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'missing slug' => [['slug' => ''], 'slug'],
        'malformed slug' => [['slug' => 'Not A Slug!'], 'slug'],
        'description too long' => [['description' => str_repeat('a', 501)], 'description'],
    ]);
});

describe('updating', function (): void {
    it('persists the editable fields', function (): void {
        $service = ServiceEloquentModel::factory()->create(['name' => 'Before']);

        $this->actingAs(serviceOperator(['UPDATE_SERVICES']))
            ->putJson(route('services.admin.update', $service->uuid), servicePayload(['name' => 'After']))
            ->assertOk()
            ->assertJsonPath('name', 'After');

        expect($service->refresh()->name)->toBe('After');
    });

    it('allows a service to keep its own slug on update', function (): void {
        $service = ServiceEloquentModel::factory()->create(['slug' => 'landing_page']);

        $this->actingAs(serviceOperator(['UPDATE_SERVICES']))
            ->putJson(route('services.admin.update', $service->uuid), servicePayload(['slug' => 'landing_page']))
            ->assertOk();
    });

    it('records the change in the audit trail', function (): void {
        $service = ServiceEloquentModel::factory()->create(['name' => 'Before']);

        $this->actingAs(serviceOperator(['UPDATE_SERVICES']))
            ->putJson(route('services.admin.update', $service->uuid), servicePayload(['name' => 'After']))
            ->assertOk();

        $activity = $service->activitiesAsSubject()->where('event', 'updated')->latest('id')->first();
        $changes = $activity?->attribute_changes?->toArray() ?? [];

        expect($activity?->log_name)->toBe('services.service')
            ->and($changes['attributes']['name'] ?? null)->toBe('After');
    });
});

describe('deleting and restoring', function (): void {
    it('soft-deletes a service', function (): void {
        $service = ServiceEloquentModel::factory()->create();

        $this->actingAs(serviceOperator(['DELETE_SERVICES']))
            ->deleteJson(route('services.admin.destroy', $service->uuid))
            ->assertNoContent();

        expect(ServiceEloquentModel::query()->whereKey($service->getKey())->exists())->toBeFalse()
            ->and(ServiceEloquentModel::withTrashed()->whereKey($service->getKey())->exists())->toBeTrue();
    });

    it('restores a soft-deleted service', function (): void {
        $service = ServiceEloquentModel::factory()->create();
        $service->delete();

        $this->actingAs(serviceOperator(['RESTORE_SERVICES']))
            ->patchJson(route('services.admin.restore', $service->uuid))
            ->assertOk()
            ->assertJsonPath('deleted_at', null);

        expect($service->fresh())->not->toBeNull();
    });
});

describe('bulk operations', function (): void {
    it('bulk soft-deletes the selected services', function (): void {
        $services = ServiceEloquentModel::factory()->count(3)->create();

        $this->actingAs(serviceOperator(['BULK_DELETE_SERVICES']))
            ->postJson(route('services.admin.bulk-delete'), ['uuids' => $services->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('deleted', 3);

        expect(ServiceEloquentModel::query()->count())->toBe(0)
            ->and(ServiceEloquentModel::withTrashed()->count())->toBe(3);
    });

    it('bulk restores the selected services', function (): void {
        $services = ServiceEloquentModel::factory()->count(2)->create();
        ServiceEloquentModel::query()->whereIn('uuid', $services->pluck('uuid'))->delete();

        $this->actingAs(serviceOperator(['BULK_RESTORE_SERVICES']))
            ->postJson(route('services.admin.bulk-restore'), ['uuids' => $services->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('restored', 2);

        expect(ServiceEloquentModel::query()->count())->toBe(2);
    });

    it('refuses bulk delete without the bulk permission', function (): void {
        $service = ServiceEloquentModel::factory()->create();

        $this->actingAs(serviceOperator(['DELETE_SERVICES']))
            ->postJson(route('services.admin.bulk-delete'), ['uuids' => [$service->uuid]])
            ->assertForbidden();
    });

    it('rejects an empty uuid list', function (): void {
        $this->actingAs(serviceOperator(['BULK_DELETE_SERVICES']))
            ->postJson(route('services.admin.bulk-delete'), ['uuids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids');
    });

    it('rejects a malformed uuid in the list', function (): void {
        $service = ServiceEloquentModel::factory()->create();

        $this->actingAs(serviceOperator(['BULK_DELETE_SERVICES']))
            ->postJson(route('services.admin.bulk-delete'), ['uuids' => [$service->uuid, 'not-a-uuid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids.1');
    });
});
