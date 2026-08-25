<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Fortify\Features;
use Modules\Auth\Domain\Ports\TrustedDevicePort;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;
use Modules\Auth\Infrastructure\Security\CookieTrustedDeviceRegistry;

/**
 * Spec 001 US-04 / FR-08 — "trust this device for 30 days".
 */
const TEST_USER_AGENT = 'PestBrowser/1.0';

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);

    $this->withHeader('User-Agent', TEST_USER_AGENT);
});

/**
 * The marker a trusted browser carries back, built exactly the way the registry
 * builds it — for the fingerprint of the test client.
 */
function trustedDeviceCookie(User $user): string
{
    return json_encode([
        'user' => (string) $user->uuid,
        'device' => DeviceFingerprint::fromRequestSignals(TEST_USER_AGENT, '127.0.0.1')->hash,
    ], JSON_THROW_ON_ERROR);
}

test('a user with confirmed 2FA is challenged on an untrusted device', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('a trusted device skips the challenge', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->withCookie(CookieTrustedDeviceRegistry::COOKIE_NAME, trustedDeviceCookie($user))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticated();
});

test('a marker issued to another user is ignored', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    $someoneElse = User::factory()->create();

    $this->withCookie(CookieTrustedDeviceRegistry::COOKIE_NAME, trustedDeviceCookie($someoneElse))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('a marker for another device is ignored', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $otherDevice = json_encode([
        'user' => (string) $user->uuid,
        'device' => DeviceFingerprint::fromRequestSignals('SomeOtherBrowser/9', '203.0.113.10')->hash,
    ], JSON_THROW_ON_ERROR);

    $this->withCookie(CookieTrustedDeviceRegistry::COOKIE_NAME, $otherDevice)
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('a malformed marker is ignored', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->withCookie(CookieTrustedDeviceRegistry::COOKIE_NAME, 'not-json')
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('trusting a device issues the marker', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->post(route('auth.two-factor.trusted-device'))
        ->assertRedirect()
        ->assertCookie(CookieTrustedDeviceRegistry::COOKIE_NAME);
});

test('a user without confirmed 2FA cannot trust a device', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('auth.two-factor.trusted-device'))
        ->assertForbidden();
});

test('signing out everywhere else drops the trusted-device marker', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('USER');

    $this->actingAs($user)->post(route('auth.two-factor.trusted-device'));

    $this->post(route('auth.sessions.revoke-others'))
        ->assertCookieExpired(CookieTrustedDeviceRegistry::COOKIE_NAME);
});

test('the registry refuses a device it has never seen', function (): void {
    $user = User::factory()->create();
    $device = DeviceFingerprint::fromRequestSignals('Mozilla/5.0', '203.0.113.10');

    expect(app(TrustedDevicePort::class)->isTrusted((string) $user->uuid, $device))->toBeFalse();
});
