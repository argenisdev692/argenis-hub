<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * Both sides of the `services.user_id` foreign key — same reasoning as
 * `CompanyRelationsTest`: `ServiceEloquentModel::user()` and `User::services()`
 * must both exist, and this is the test that keeps a future refactor honest.
 */
it('resolves the owner from the service record', function (): void {
    $owner = User::factory()->create();
    $service = ServiceEloquentModel::factory()->create(['user_id' => $owner->id]);

    expect($service->user)->toBeInstanceOf(User::class)
        ->and($service->user?->getKey())->toBe($owner->getKey());
});

it('resolves the services collection from the owner', function (): void {
    $owner = User::factory()->create();
    $service = ServiceEloquentModel::factory()->create(['user_id' => $owner->id]);

    expect($owner->services)->toHaveCount(1)
        ->and($owner->services->first()?->getKey())->toBe($service->getKey());
});

it('eager-loads both directions without a lazy-loading violation', function (): void {
    $owner = User::factory()->create();
    ServiceEloquentModel::factory()->create(['user_id' => $owner->id]);

    $loaded = User::query()->with('services:id,user_id,name')->findOrFail($owner->getKey());
    expect($loaded->services->first()?->name)->toBeString();

    $child = ServiceEloquentModel::query()->with('user:id,first_name')->firstOrFail();
    expect($child->user?->first_name)->toBeString();
});

it('leaves the owner alone when a service is soft-deleted', function (): void {
    $owner = User::factory()->create();
    $service = ServiceEloquentModel::factory()->create(['user_id' => $owner->id]);

    $service->delete();

    expect(User::query()->whereKey($owner->getKey())->exists())->toBeTrue()
        ->and(ServiceEloquentModel::withTrashed()->whereKey($service->getKey())->exists())->toBeTrue();
});
