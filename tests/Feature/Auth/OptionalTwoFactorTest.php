<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Spec 001 US-04 — two-factor authentication is opt-in for EVERY role.
 *
 * Supersedes the earlier forced-enrolment policy: no role is held at the door,
 * and enrolment happens from Settings → Security when the user chooses to.
 * `config('auth-security.privileged_roles')` now only drives the shorter idle
 * session lifetime, never a 2FA gate.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function userWithRole(string $role, bool $withTwoFactor = false): User
{
    $factory = $withTwoFactor ? User::factory()->withTwoFactor() : User::factory();

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('an admin without 2FA reaches the dashboard', function (): void {
    $this->actingAs(userWithRole('ADMIN'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('a superadmin without 2FA reaches the dashboard', function (): void {
    $this->actingAs(userWithRole('SUPER_ADMIN'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('an admin who started but never confirmed enrolment is not held back', function (): void {
    $user = User::factory()->withUnconfirmedTwoFactor()->create();
    $user->assignRole('ADMIN');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('an admin who opted in to 2FA still reaches the dashboard', function (): void {
    $this->actingAs(userWithRole('ADMIN', withTwoFactor: true))
        ->get(route('dashboard'))
        ->assertOk();
});

test('a standard user without 2FA reaches the dashboard', function (): void {
    $this->actingAs(userWithRole('USER'))
        ->get(route('dashboard'))
        ->assertOk();
});

test('an api client is not refused for lacking 2FA', function (): void {
    $this->actingAs(userWithRole('ADMIN'))
        ->getJson(route('dashboard'))
        ->assertSuccessful();
});
