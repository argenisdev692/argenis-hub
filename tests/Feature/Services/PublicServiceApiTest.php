<?php

declare(strict_types=1);

use Modules\Services\Infrastructure\Cache\ServicePublicFeedCache;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * `GET /api/public/services` — the payload the landing page `<select>` reads.
 * No authentication, so the allowlist and cache-invalidation behavior are the
 * load-bearing assertions, same reasoning as `PublicCompanyApiTest`.
 */
beforeEach(function (): void {
    ServicePublicFeedCache::flush();
});

it('is reachable without authentication', function (): void {
    ServiceEloquentModel::factory()->create();

    $this->getJson(route('api.public.services'))->assertOk();
});

it('lists only active, non-deleted services, ordered by sort_order', function (): void {
    ServiceEloquentModel::factory()->create(['name' => 'Second', 'sort_order' => 1]);
    ServiceEloquentModel::factory()->create(['name' => 'First', 'sort_order' => 0]);
    ServiceEloquentModel::factory()->inactive()->create(['name' => 'Hidden']);
    ServiceEloquentModel::factory()->create(['name' => 'Deleted'])->delete();

    $this->getJson(route('api.public.services'))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.name', 'First')
        ->assertJsonPath('1.name', 'Second');
});

it('never leaks the owner id or lifecycle timestamps', function (): void {
    ServiceEloquentModel::factory()->create();

    $body = $this->getJson(route('api.public.services'))->assertOk()->getContent() ?: '';

    expect($body)
        ->not->toContain('"user_id"')
        ->not->toContain('"created_at"')
        ->not->toContain('"is_active"');
});

it('serves later callers from the cache', function (): void {
    ServiceEloquentModel::factory()->create(['name' => 'Before']);

    $this->getJson(route('api.public.services'))->assertJsonPath('0.name', 'Before');

    // Straight to the database, so no model event fires and no cache is flushed.
    ServiceEloquentModel::query()->update(['name' => 'After']);

    $this->getJson(route('api.public.services'))->assertJsonPath('0.name', 'Before');
});

it('flushes the cache the moment a service is saved', function (): void {
    $service = ServiceEloquentModel::factory()->create(['name' => 'Before']);

    $this->getJson(route('api.public.services'))->assertJsonPath('0.name', 'Before');

    $service->update(['name' => 'After']);

    $this->getJson(route('api.public.services'))->assertJsonPath('0.name', 'After');
});

it('throttles an unauthenticated caller', function (): void {
    ServiceEloquentModel::factory()->create();

    foreach (range(1, 60) as $ignored) {
        $this->getJson(route('api.public.services'))->assertOk();
    }

    $this->getJson(route('api.public.services'))->assertStatus(429);
});
