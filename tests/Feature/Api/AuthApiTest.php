<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Domain\Ports\AccountLockPort;

/**
 * Spec 001 §8 — Sanctum token endpoints for mobile / external clients.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function apiUser(string $role = 'USER'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function apiLogin(User $user, array $overrides = []): TestResponse
{
    return test()->postJson(route('api.auth.login'), array_merge([
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 8',
    ], $overrides));
}

test('valid credentials return a bearer token and the identity', function (): void {
    $user = apiUser();

    $response = apiLogin($user)->assertCreated();

    $response->assertJsonStructure([
        'token' => ['access_token', 'token_type', 'expires_at', 'abilities'],
        'user' => ['uuid', 'name', 'email', 'email_verified_at', 'two_factor_enabled', 'roles', 'permissions'],
    ]);

    expect($response->json('token.token_type'))->toBe('Bearer')
        ->and($response->json('user.email'))->toBe($user->email)
        ->and($user->tokens()->count())->toBe(1);
});

test('the token response never leaks the password or the 2FA secret', function (): void {
    $response = apiLogin(apiUser())->assertCreated();

    expect($response->json('user'))
        ->not->toHaveKey('password')
        ->not->toHaveKey('two_factor_secret')
        ->not->toHaveKey('two_factor_recovery_codes')
        ->not->toHaveKey('id');
});

test('wrong credentials are rejected without revealing whether the account exists', function (): void {
    $user = apiUser();

    $known = apiLogin($user, ['password' => 'wrong-password'])->assertStatus(422);
    $unknown = apiLogin($user, ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertStatus(422);

    expect($known->json('message'))->toBe($unknown->json('message'));
});

test('an unverified email cannot obtain a token', function (): void {
    $user = User::factory()->unverified()->create();
    $user->assignRole('USER');

    apiLogin($user)->assertStatus(422);

    expect($user->tokens()->count())->toBe(0);
});

test('a locked account is refused before the password is even checked', function (): void {
    $user = apiUser();

    app(AccountLockPort::class)->lock(
        (string) $user->email,
        new DateTimeImmutable('+15 minutes'),
    );

    apiLogin($user)->assertStatus(423);

    expect($user->tokens()->count())->toBe(0);
});

test('logging in again rotates the token for the same device', function (): void {
    $user = apiUser();

    $first = apiLogin($user)->assertCreated()->json('token.access_token');
    $second = apiLogin($user)->assertCreated()->json('token.access_token');

    expect($first)->not->toBe($second)
        ->and($user->tokens()->count())->toBe(1);

    $this->withHeader('Authorization', "Bearer {$first}")
        ->getJson(route('api.auth.me'))
        ->assertUnauthorized();
});

test('a second device keeps its own token', function (): void {
    $user = apiUser();

    apiLogin($user)->assertCreated();
    apiLogin($user, ['device_name' => 'iPad'])->assertCreated();

    expect($user->tokens()->count())->toBe(2);
});

test('the token expires sooner for a privileged role', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-25 10:00:00'));

    $standard = apiLogin(apiUser())->json('token.expires_at');
    $admin = apiLogin(apiUser('ADMIN'))->json('token.expires_at');

    expect(Carbon::parse($standard)->diffInMinutes(Carbon::now(), absolute: true))->toEqual(1440)
        ->and(Carbon::parse($admin)->diffInMinutes(Carbon::now(), absolute: true))->toEqual(60);

    Carbon::setTestNow();
});

test('me returns the caller identity behind the token', function (): void {
    $user = apiUser();
    $token = apiLogin($user)->json('token.access_token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.auth.me'))
        ->assertOk()
        ->assertJsonPath('user.uuid', (string) $user->uuid);
});

test('refresh destroys the presented token and issues a new one', function (): void {
    $user = apiUser();
    $old = apiLogin($user)->json('token.access_token');

    $new = $this->withHeader('Authorization', "Bearer {$old}")
        ->postJson(route('api.auth.refresh'))
        ->assertOk()
        ->json('token.access_token');

    expect($new)->not->toBe($old)
        ->and($user->tokens()->count())->toBe(1)
        ->and(PersonalAccessToken::findToken($old))->toBeNull();

    // The guard caches the user it resolved during the refresh request, and the
    // test app is never rebooted between requests — so without dropping the
    // resolved guards the next call would pass on that cache rather than on the
    // bearer actually presented.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$old}")
        ->getJson(route('api.auth.me'))
        ->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$new}")
        ->getJson(route('api.auth.me'))
        ->assertOk();
});

test('logout revokes only the presented token', function (): void {
    $user = apiUser();
    $phone = apiLogin($user)->json('token.access_token');
    apiLogin($user, ['device_name' => 'iPad'])->assertCreated();

    $this->withHeader('Authorization', "Bearer {$phone}")
        ->postJson(route('api.auth.logout'))
        ->assertOk();

    expect($user->tokens()->count())->toBe(1);
});

test('an expired token is refused', function (): void {
    $user = apiUser();
    $token = apiLogin($user)->json('token.access_token');

    PersonalAccessToken::query()->update(['expires_at' => Carbon::now()->subMinute()]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.auth.me'))
        ->assertUnauthorized();
});

test('the endpoints are closed to anonymous callers', function (): void {
    $this->getJson(route('api.auth.me'))->assertUnauthorized();
    $this->postJson(route('api.auth.refresh'))->assertUnauthorized();
    $this->postJson(route('api.auth.logout'))->assertUnauthorized();
});

test('device_name is required so a token can be rotated per device', function (): void {
    apiLogin(apiUser(), ['device_name' => ''])->assertStatus(422);
});

/*
 * The API must NOT become a way around the web two-factor challenge.
 */
test('an account with 2FA cannot get a token without a code', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('USER');

    apiLogin($user)->assertStatus(422)->assertJsonValidationErrors('code');

    expect($user->tokens()->count())->toBe(0);
});

test('an account with 2FA is refused when the code is wrong', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('USER');

    apiLogin($user, ['code' => '000000'])->assertStatus(422)->assertJsonValidationErrors('code');

    expect($user->tokens()->count())->toBe(0);
});

test('a valid TOTP code completes the login', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('USER');

    $this->mock(TwoFactorAuthenticationProvider::class)
        ->shouldReceive('verify')->once()->andReturnTrue();

    apiLogin($user, ['code' => '123456'])->assertCreated();

    expect($user->tokens()->count())->toBe(1);
});

test('a recovery code completes the login and is consumed', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('USER');

    $recoveryCode = $user->recoveryCodes()[0];

    apiLogin($user, ['code' => $recoveryCode])->assertCreated();

    expect($user->fresh()->recoveryCodes())->not->toContain($recoveryCode);
});
