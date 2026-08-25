<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\AuthSessionEloquentModel;
use Spatie\Activitylog\Models\Activity;
use Spatie\OneTimePasswords\Models\OneTimePassword;

/**
 * Spec 001 US-08 / FR-17 — every authentication event is on the trail, with IP
 * address and user agent, and never with a secret.
 */
function authActivity(string $event): ?Activity
{
    return Activity::query()
        ->where('log_name', 'auth')
        ->where('event', $event)
        ->latest('id')
        ->first();
}

test('a successful login is recorded with IP and user agent', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $entry = authActivity('login');

    expect($entry)->not->toBeNull();
    expect($entry->properties->toArray())->toHaveKeys(['ip_address', 'user_agent']);
    expect($entry->causer_id)->toBe($user->id);
});

test('a failed login is recorded', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    expect(authActivity('login_failed'))->not->toBeNull();
});

test('a failed login never records the submitted password', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'sup3r-s3cret!']);

    $properties = authActivity('login_failed')->properties->toArray();

    expect(json_encode($properties))->not->toContain('sup3r-s3cret!');
    expect($properties)->not->toHaveKey('password');
});

test('a logout is recorded', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    expect(authActivity('logout'))->not->toBeNull();
});

test('registration is recorded', function (): void {
    $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'Str0ng-First-Passw0rd!',
        'password_confirmation' => 'Str0ng-First-Passw0rd!',
    ]);

    expect(authActivity('registered'))->not->toBeNull();
});

test('email verification is recorded', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'));
    $code = (string) OneTimePassword::query()->latest('id')->value('password');

    $this->post(route('auth.email.verify-code'), ['code' => $code]);

    expect(authActivity('email_verified'))->not->toBeNull();
});

test('a password change is recorded', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'Str0ng-Fresh-Passw0rd!',
            'password_confirmation' => 'Str0ng-Fresh-Passw0rd!',
        ]);

    expect(authActivity('password_changed'))->not->toBeNull();
});

test('a new device is recorded', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('dashboard'));

    expect(authActivity('new_device_detected'))->not->toBeNull();
});

test('a lockout is recorded with the attempt count and the expiry', function (): void {
    config(['auth-security.lockout.max_attempts' => 1]);

    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $entry = authActivity('account_locked_out');

    expect($entry)->not->toBeNull();
    expect($entry->properties->toArray())->toHaveKeys(['attempted_email', 'failed_attempts', 'locked_until']);
});

test('a revoked session leaves an entry on the session log', function (): void {
    $user = User::factory()->create();
    $session = AuthSessionEloquentModel::factory()
        ->for($user)
        ->create();

    $session->update(['revoked_at' => now()]);

    expect(Activity::query()->where('log_name', 'auth.session')->exists())->toBeTrue();
});
