<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Auth\Infrastructure\Notifications\NewDeviceDetectedNotification;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\AuthSessionEloquentModel;

/**
 * Spec 001 US-07 / FR-14, FR-15 — session and device management.
 *
 * A session is tracked on the first authenticated request, not inside the login
 * request itself: the framework regenerates the session id twice while signing
 * in, so the id is only final afterwards. Tests therefore follow the login with
 * a real page request.
 */
function sessionUser(): User
{
    test()->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('USER');

    return $user;
}

/**
 * Pins the session id that is live right now onto every following request.
 *
 * The test client never harvests cookies from a response, and the `array`
 * session driver mints a fresh id per request, so by default each test request
 * lands in a brand new session. Authentication still survives in memory, which
 * hides the problem — until a test asserts on behaviour keyed to the session id
 * itself (revocation, logout), which then silently observes the wrong session.
 * Anything that must stay inside ONE session has to say so explicitly.
 */
function keepCurrentSession(): void
{
    test()->withCookie(config('session.cookie'), app('session')->getId());
}

test('the first authenticated request starts tracking the session', function (): void {
    $user = sessionUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('auth.sessions.index'))->assertOk();

    expect($user->authSessions()->whereNull('revoked_at')->count())->toBe(1);
});

test('a login from an unfamiliar device alerts the owner', function (): void {
    Notification::fake();

    $user = sessionUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('auth.sessions.index'));

    Notification::assertSentTo($user, NewDeviceDetectedNotification::class);
});

test('a login from a device already seen does not alert again', function (): void {
    $user = sessionUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('auth.sessions.index'));
    $this->post(route('logout'));

    Notification::fake();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('auth.sessions.index'));

    Notification::assertNotSentTo($user, NewDeviceDetectedNotification::class);
});

test('the sessions page lists the active sessions', function (): void {
    $user = sessionUser();
    AuthSessionEloquentModel::factory()->for($user)->count(2)->create();

    $this->actingAs($user)
        ->get(route('auth.sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Sessions')
            // The two seeded sessions plus the one this request itself opened.
            ->has('sessions', 3),
        );
});

test('the current session is listed first and flagged', function (): void {
    $user = sessionUser();
    AuthSessionEloquentModel::factory()->for($user)->create();

    $first = $this->actingAs($user)
        ->getJson(route('auth.sessions.index'))
        ->assertOk()
        ->json('data.0');

    expect($first['is_current'])->toBeTrue();
});

test('the sessions payload never leaks the framework session id or device hash', function (): void {
    $user = sessionUser();

    $payload = $this->actingAs($user)
        ->getJson(route('auth.sessions.index'))
        ->assertOk()
        ->json('data.0');

    expect($payload)->not->toHaveKey('session_id');
    expect($payload)->not->toHaveKey('device_hash');
    expect($payload)->toHaveKeys(['uuid', 'ip_address', 'user_agent', 'last_seen_at', 'created_at', 'is_current']);
});

test('a user can revoke one of their own sessions', function (): void {
    $user = sessionUser();
    $session = AuthSessionEloquentModel::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('auth.sessions.destroy', $session->uuid))
        ->assertRedirect();

    expect($session->fresh()->revoked_at)->not->toBeNull();
});

test('a user cannot revoke somebody else session', function (): void {
    $user = sessionUser();
    $stranger = AuthSessionEloquentModel::factory()->for(User::factory())->create();

    $this->actingAs($user)
        ->delete(route('auth.sessions.destroy', $stranger->uuid))
        ->assertNotFound();

    expect($stranger->fresh()->revoked_at)->toBeNull();
});

test('revoke-others ends every session except the current one', function (): void {
    $user = sessionUser();
    AuthSessionEloquentModel::factory()->for($user)->count(3)->create();

    $this->actingAs($user)->get(route('auth.sessions.index'));
    $this->post(route('auth.sessions.revoke-others'));

    expect($user->authSessions()->whereNull('revoked_at')->count())->toBe(1);
});

test('a revoked session is signed out on its next request', function (): void {
    $user = sessionUser();

    $this->actingAs($user)->get(route('auth.sessions.index'))->assertOk();
    keepCurrentSession();

    $user->authSessions()->update(['revoked_at' => now()]);

    $this->get(route('auth.sessions.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('signing out closes the tracked session', function (): void {
    $user = sessionUser();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('auth.sessions.index'));
    keepCurrentSession();

    $this->post(route('logout'));

    expect($user->authSessions()->whereNull('revoked_at')->count())->toBe(0);
});

test('the sessions page is closed to guests', function (): void {
    $this->get(route('auth.sessions.index'))->assertRedirect(route('login'));
});

test('a role without the permission is refused', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('GUEST');

    $this->actingAs($user)->get(route('auth.sessions.index'))->assertForbidden();
});
