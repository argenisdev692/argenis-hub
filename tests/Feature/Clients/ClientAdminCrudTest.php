<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * `/data/admin/clients` — the JSON CRUD surface the CRM admin table consumes.
 * Every write is guarded by its own `*_CLIENTS` permission (seeded ahead of
 * this module in `RolePermissionSeeder::MODULES`).
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<string>  $permissions
 */
function clientOperator(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientPayload(array $overrides = []): array
{
    return [
        'client_name' => 'IMAGINA WEB & MOBILE TECHNOLOGIES S.L.',
        'email' => 'info@imagina.example',
        'status' => 'ACTIVE',
        'phone' => '+34673566782',
        'address' => 'Avda. Manuel de Falla, 12, 46015 Valencia',
        'country' => 'Spain',
        'country_code' => 'ES',
        'tax_id' => '0',
        'nif' => 'B98330335',
        'website' => 'https://imagina.example',
        'facebook_link' => 'https://facebook.com/imagina',
        'notes' => 'Recurring client used for invoices.',
        ...$overrides,
    ];
}

describe('authorization', function (): void {
    it('turns a guest away from every route', function (): void {
        $client = ClientEloquentModel::factory()->create();

        $this->getJson(route('clients.admin.index'))->assertUnauthorized();
        $this->getJson(route('clients.admin.show', $client->uuid))->assertUnauthorized();
        $this->postJson(route('clients.admin.store'), clientPayload())->assertUnauthorized();
        $this->putJson(route('clients.admin.update', $client->uuid), clientPayload())->assertUnauthorized();
        $this->deleteJson(route('clients.admin.destroy', $client->uuid))->assertUnauthorized();
        $this->patchJson(route('clients.admin.restore', $client->uuid))->assertUnauthorized();
        $this->postJson(route('clients.admin.bulk-delete'), ['uuids' => [$client->uuid]])->assertUnauthorized();
        $this->postJson(route('clients.admin.bulk-restore'), ['uuids' => [$client->uuid]])->assertUnauthorized();
    });

    it('refuses a signed-in user who holds no clients permission', function (): void {
        $this->actingAs(clientOperator([]))
            ->getJson(route('clients.admin.index'))
            ->assertForbidden();
    });

    it('refuses a reader on the write routes', function (): void {
        $client = ClientEloquentModel::factory()->create();

        $this->actingAs(clientOperator(['VIEW_ANY_CLIENTS', 'VIEW_CLIENTS']))
            ->putJson(route('clients.admin.update', $client->uuid), clientPayload())
            ->assertForbidden();
    });
});

describe('listing', function (): void {
    it('paginates active and soft-deleted clients together by default', function (): void {
        ClientEloquentModel::factory()->count(2)->create();
        ClientEloquentModel::factory()->create()->delete();

        $this->actingAs(clientOperator(['VIEW_ANY_CLIENTS']))
            ->getJson(route('clients.admin.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('serializes the paginator flat, not nested under a "meta" key', function (): void {
        ClientEloquentModel::factory()->create();

        $this->actingAs(clientOperator(['VIEW_ANY_CLIENTS']))
            ->getJson(route('clients.admin.index'))
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
        ClientEloquentModel::factory()->create(['client_name' => 'AQUASHIELD RESTORATION LLC']);
        ClientEloquentModel::factory()->create(['client_name' => 'CESAR AUGUSTO GONZALEZ']);

        $this->actingAs(clientOperator(['VIEW_ANY_CLIENTS']))
            ->getJson(route('clients.admin.index', ['search' => 'aquashield']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_name', 'AQUASHIELD RESTORATION LLC');
    });

    it('never exposes the auto-increment key or the owner column', function (): void {
        ClientEloquentModel::factory()->create();

        $response = $this->actingAs(clientOperator(['VIEW_ANY_CLIENTS']))
            ->getJson(route('clients.admin.index'))
            ->assertOk();

        expect($response->json('data.0'))
            ->not->toHaveKey('id')
            ->not->toHaveKey('user_id');
    });
});

describe('creating', function (): void {
    it('creates a client owned by the acting operator', function (): void {
        $operator = clientOperator(['CREATE_CLIENTS']);

        $this->actingAs($operator)
            ->postJson(route('clients.admin.store'), clientPayload())
            ->assertCreated()
            ->assertJsonPath('client_name', 'IMAGINA WEB & MOBILE TECHNOLOGIES S.L.')
            ->assertJsonPath('status', 'ACTIVE');

        $client = ClientEloquentModel::query()->where('nif', 'B98330335')->firstOrFail();
        expect($client->user_id)->toBe($operator->id);
    });

    it('defaults a new client without a status to DRAFT', function (): void {
        $this->actingAs(clientOperator(['CREATE_CLIENTS']))
            ->postJson(route('clients.admin.store'), clientPayload(['status' => null]))
            ->assertCreated()
            ->assertJsonPath('status', 'DRAFT');
    });

    it('rejects invalid input', function (array $overrides, string $field): void {
        $this->actingAs(clientOperator(['CREATE_CLIENTS']))
            ->postJson(route('clients.admin.store'), clientPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'missing name' => [['client_name' => ''], 'client_name'],
        'malformed email' => [['email' => 'not-an-email'], 'email'],
        'missing phone' => [['phone' => ''], 'phone'],
        'malformed phone' => [['phone' => 'call-me'], 'phone'],
        'unknown status' => [['status' => 'PROSPECT'], 'status'],
        'malformed website' => [['website' => 'imagina dot example'], 'website'],
        'malformed country code' => [['country_code' => 'ESP'], 'country_code'],
        'notes too long' => [['notes' => str_repeat('a', 5001)], 'notes'],
    ]);
});

describe('updating', function (): void {
    it('persists the editable fields', function (): void {
        $client = ClientEloquentModel::factory()->create(['client_name' => 'Before']);

        $this->actingAs(clientOperator(['UPDATE_CLIENTS']))
            ->putJson(route('clients.admin.update', $client->uuid), clientPayload(['client_name' => 'After']))
            ->assertOk()
            ->assertJsonPath('client_name', 'After');

        expect($client->refresh()->client_name)->toBe('After');
    });

    it('records the change in the audit trail', function (): void {
        $client = ClientEloquentModel::factory()->create(['client_name' => 'Before']);

        $this->actingAs(clientOperator(['UPDATE_CLIENTS']))
            ->putJson(route('clients.admin.update', $client->uuid), clientPayload(['client_name' => 'After']))
            ->assertOk();

        $activity = $client->activitiesAsSubject()->where('event', 'updated')->latest('id')->first();
        $changes = $activity?->attribute_changes?->toArray() ?? [];

        expect($activity?->log_name)->toBe('clients.client')
            ->and($changes['attributes']['client_name'] ?? null)->toBe('After');
    });
});

describe('deleting and restoring', function (): void {
    it('soft-deletes a client', function (): void {
        $client = ClientEloquentModel::factory()->create();

        $this->actingAs(clientOperator(['DELETE_CLIENTS']))
            ->deleteJson(route('clients.admin.destroy', $client->uuid))
            ->assertNoContent();

        expect(ClientEloquentModel::query()->whereKey($client->getKey())->exists())->toBeFalse()
            ->and(ClientEloquentModel::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
    });

    it('restores a soft-deleted client', function (): void {
        $client = ClientEloquentModel::factory()->create();
        $client->delete();

        $this->actingAs(clientOperator(['RESTORE_CLIENTS']))
            ->patchJson(route('clients.admin.restore', $client->uuid))
            ->assertOk()
            ->assertJsonPath('deleted_at', null);

        expect($client->fresh())->not->toBeNull();
    });
});

describe('bulk operations', function (): void {
    it('bulk soft-deletes the selected clients', function (): void {
        $clients = ClientEloquentModel::factory()->count(3)->create();

        $this->actingAs(clientOperator(['BULK_DELETE_CLIENTS']))
            ->postJson(route('clients.admin.bulk-delete'), ['uuids' => $clients->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('deleted', 3);

        expect(ClientEloquentModel::query()->count())->toBe(0)
            ->and(ClientEloquentModel::withTrashed()->count())->toBe(3);
    });

    it('bulk restores the selected clients', function (): void {
        $clients = ClientEloquentModel::factory()->count(2)->create();
        ClientEloquentModel::query()->whereIn('uuid', $clients->pluck('uuid'))->delete();

        $this->actingAs(clientOperator(['BULK_RESTORE_CLIENTS']))
            ->postJson(route('clients.admin.bulk-restore'), ['uuids' => $clients->pluck('uuid')->all()])
            ->assertOk()
            ->assertJsonPath('restored', 2);

        expect(ClientEloquentModel::query()->count())->toBe(2);
    });

    it('refuses bulk delete without the bulk permission', function (): void {
        $client = ClientEloquentModel::factory()->create();

        $this->actingAs(clientOperator(['DELETE_CLIENTS']))
            ->postJson(route('clients.admin.bulk-delete'), ['uuids' => [$client->uuid]])
            ->assertForbidden();
    });

    it('rejects an empty uuid list', function (): void {
        $this->actingAs(clientOperator(['BULK_DELETE_CLIENTS']))
            ->postJson(route('clients.admin.bulk-delete'), ['uuids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids');
    });

    it('rejects a malformed uuid in the list', function (): void {
        $client = ClientEloquentModel::factory()->create();

        $this->actingAs(clientOperator(['BULK_DELETE_CLIENTS']))
            ->postJson(route('clients.admin.bulk-delete'), ['uuids' => [$client->uuid, 'not-a-uuid']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('uuids.1');
    });
});
