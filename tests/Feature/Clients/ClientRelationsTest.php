<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;

/**
 * Both sides of the `clients.user_id` foreign key — same reasoning as
 * `ServiceRelationsTest`: `ClientEloquentModel::user()` and `User::clients()`
 * must both exist, and this is the test that keeps a future refactor honest.
 */
it('resolves the owner from the client record', function (): void {
    $owner = User::factory()->create();
    $client = ClientEloquentModel::factory()->create(['user_id' => $owner->id]);

    expect($client->user)->toBeInstanceOf(User::class)
        ->and($client->user?->getKey())->toBe($owner->getKey());
});

it('resolves the clients collection from the owner', function (): void {
    $owner = User::factory()->create();
    $client = ClientEloquentModel::factory()->create(['user_id' => $owner->id]);

    expect($owner->clients)->toHaveCount(1)
        ->and($owner->clients->first()?->getKey())->toBe($client->getKey());
});

it('eager-loads both directions without a lazy-loading violation', function (): void {
    $owner = User::factory()->create();
    ClientEloquentModel::factory()->create(['user_id' => $owner->id]);

    $loaded = User::query()->with('clients:id,user_id,client_name')->findOrFail($owner->getKey());
    expect($loaded->clients->first()?->client_name)->toBeString();

    $child = ClientEloquentModel::query()->with('user:id,first_name')->firstOrFail();
    expect($child->user?->first_name)->toBeString();
});

it('leaves the owner alone when a client is soft-deleted', function (): void {
    $owner = User::factory()->create();
    $client = ClientEloquentModel::factory()->create(['user_id' => $owner->id]);

    $client->delete();

    expect(User::query()->whereKey($owner->getKey())->exists())->toBeTrue()
        ->and(ClientEloquentModel::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
});
