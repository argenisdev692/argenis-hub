<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Auth\Domain\Ports\AccountLockPort;
use Modules\Auth\Domain\Ports\LoginAttemptTrackerPort;

/**
 * Spec 001 US-03 / FR-05 / FR-06 — brute-force lockout.
 *
 * Fortify's own 5/min limiter would trip long before the 10th failure, so these
 * tests drive the counter through the module's ports and then assert what the
 * HTTP layer does with the resulting lock. That keeps each control under test in
 * isolation instead of measuring the two together.
 */
function failLoginTimes(User $user, int $times): void
{
    foreach (range(1, $times) as $ignored) {
        RateLimiter::clear(md5('login'.implode('|', [$user->email, '127.0.0.1'])));

        test()->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }
}

test('a wrong password increments the failure counter', function (): void {
    $user = User::factory()->create();

    failLoginTimes($user, 1);

    expect(app(LoginAttemptTrackerPort::class)->failures($user->email))->toBe(1);
});

test('the account is locked once the threshold is crossed', function (): void {
    config(['auth-security.lockout.max_attempts' => 3]);

    $user = User::factory()->create();

    failLoginTimes($user, 3);

    expect(app(AccountLockPort::class)->lockedUntil($user->email))->not->toBeNull();
    expect($user->fresh()->locked_until)->not->toBeNull();
});

test('a locked account is refused with 429 and Retry-After even with the correct password', function (): void {
    $user = User::factory()->locked()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertTooManyRequests();
    expect($response->headers->get('Retry-After'))->not->toBeNull();
    $this->assertGuest();
});

test('an expired lock lets the user back in', function (): void {
    $user = User::factory()->create();
    $user->forceFill(['locked_until' => now()->subMinute()])->save();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('a successful login clears the failure counter and the lock', function (): void {
    config(['auth-security.lockout.max_attempts' => 50]);

    $user = User::factory()->create();

    failLoginTimes($user, 2);
    RateLimiter::clear(md5('login'.implode('|', [$user->email, '127.0.0.1'])));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    expect(app(LoginAttemptTrackerPort::class)->failures($user->email))->toBe(0);
    expect($user->fresh()->locked_until)->toBeNull();
});

test('an unknown address is never locked, so the response cannot enumerate accounts', function (): void {
    config(['auth-security.lockout.max_attempts' => 2]);

    foreach (range(1, 3) as $attempt) {
        RateLimiter::clear(md5('login'.implode('|', ['ghost@example.com', '127.0.0.1'])));

        $response = $this->post(route('login.store'), [
            'email' => 'ghost@example.com',
            'password' => 'wrong-password',
        ]);
    }

    expect(app(AccountLockPort::class)->lockedUntil('ghost@example.com'))->toBeNull();
    $response->assertSessionHasErrors('email');
});
