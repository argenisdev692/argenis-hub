<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Authorization must decide before validation does.
 *
 * The list endpoints inject their filter `Data` object so Scramble can document
 * the query parameters (it reads a `Data` parameter's rules, but cannot follow
 * `validateAndCreate($request)`). Injection resolves — and therefore validates —
 * during method resolution, which is *before* the controller body runs.
 *
 * On endpoints whose permission check lives in the body as `abort_unless(...)`,
 * that reordering would let a signed-in caller who holds no permission probe the
 * filter surface: send a bad value, read the 422, and learn a parameter's name
 * and its allowed values from an endpoint they cannot call. A 403 leaks nothing.
 *
 * These endpoints therefore carry `permission:*` route middleware, which runs
 * ahead of resolution and keeps 403 the answer either way. This test is what
 * stops that middleware from being dropped as "redundant" with the in-body
 * check — it is not redundant, it is what fixes the ordering.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    // Authenticated, but holding no permission on any of the endpoints below.
    $this->outsider = User::factory()->create();
});

dataset('guarded list endpoints', [
    'roles' => ['/api/roles', 'status'],
    'permissions' => ['/api/permissions', 'status'],
    'blog categories' => ['/api/blog-categories', 'status'],
    'campaigns' => ['/api/campaigns', 'status'],
    'posts' => ['/api/posts', 'status'],
    'social media' => ['/api/social-media', 'status'],
    'cvs' => ['/api/cvs', 'status'],
    'availability rules' => ['/api/availability-rules', 'availability'],
    'availability exceptions' => ['/api/availability-exceptions', 'availability'],
    'invoices' => ['/api/invoices', 'status'],
    // Injected its filter DTO long before the others did, and so had this same
    // hole without anything pointing at it.
    'activity logs' => ['/api/activity-logs', 'sort_direction'],
]);

it('answers 403, not 422, when an unauthorized caller sends a bad filter', function (
    string $endpoint,
    string $filter,
): void {
    $this->actingAs($this->outsider, 'sanctum')
        ->getJson("{$endpoint}?{$filter}=__not_a_valid_value__")
        ->assertForbidden();
})->with('guarded list endpoints');

it('answers 403 for an unauthorized caller sending no filter at all', function (
    string $endpoint,
): void {
    $this->actingAs($this->outsider, 'sanctum')
        ->getJson($endpoint)
        ->assertForbidden();
})->with('guarded list endpoints');
