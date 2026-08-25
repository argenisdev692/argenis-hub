<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Modules\Auth\Infrastructure\Notifications\AuthOneTimePasswordNotification;
use Modules\Auth\Infrastructure\Notifications\PasswordChangedNotification;
use Spatie\OneTimePasswords\Models\OneTimePassword;

/**
 * Spec 001 US-06 / FR-11..FR-13 — password reset by 6-digit code.
 *
 * These cases replace the previous signed-link/broker-token coverage: FR-11
 * rules out link and token URLs entirely, so the reset path they exercised no
 * longer exists as a user-reachable flow. Every behaviour they asserted —
 * request a reset, reset with a valid secret, reject an invalid one — is
 * asserted below against the code-based flow.
 */
beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

function requestResetCode(User $user): string
{
    test()->post(route('auth.password.reset-code'), ['email' => $user->email]);

    return (string) OneTimePassword::query()->latest('id')->value('password');
}

test('the reset request screen can be rendered', function (): void {
    $this->get(route('password.request'))->assertOk();
});

test('requesting a reset emails a 6-digit code', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('auth.password.reset-code'), ['email' => $user->email])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, AuthOneTimePasswordNotification::class);
});

test('requesting a reset for an unknown address looks identical and sends nothing', function (): void {
    Notification::fake();

    $this->post(route('auth.password.reset-code'), ['email' => 'ghost@example.com'])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
    expect(OneTimePassword::query()->count())->toBe(0);
});

test('the password can be reset with a valid code', function (): void {
    $user = User::factory()->create();
    $code = requestResetCode($user);

    $this->post(route('auth.password.reset-with-code'), [
        'email' => $user->email,
        'code' => $code,
        'password' => 'Str0ng-New-Passw0rd!',
        'password_confirmation' => 'Str0ng-New-Passw0rd!',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('Str0ng-New-Passw0rd!', $user->fresh()->password))->toBeTrue();
});

test('the password cannot be reset with a wrong code', function (): void {
    $user = User::factory()->create();
    requestResetCode($user);

    $this->post(route('auth.password.reset-with-code'), [
        'email' => $user->email,
        'code' => '000000',
        'password' => 'Str0ng-New-Passw0rd!',
        'password_confirmation' => 'Str0ng-New-Passw0rd!',
    ])->assertSessionHasErrors('code');

    expect(Hash::check('Str0ng-New-Passw0rd!', $user->fresh()->password))->toBeFalse();
});

test('an unknown address fails on the code, never on the address', function (): void {
    $this->post(route('auth.password.reset-with-code'), [
        'email' => 'ghost@example.com',
        'code' => '123456',
        'password' => 'Str0ng-New-Passw0rd!',
        'password_confirmation' => 'Str0ng-New-Passw0rd!',
    ])
        ->assertSessionHasErrors('code')
        ->assertSessionDoesntHaveErrors('email');
});

test('a weak password is rejected by the policy', function (): void {
    $user = User::factory()->create();
    $code = requestResetCode($user);

    $this->post(route('auth.password.reset-with-code'), [
        'email' => $user->email,
        'code' => $code,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');
});

test('resetting the password notifies the owner', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('auth.password.reset-code'), ['email' => $user->email]);
    $code = (string) OneTimePassword::query()->latest('id')->value('password');

    $this->post(route('auth.password.reset-with-code'), [
        'email' => $user->email,
        'code' => $code,
        'password' => 'Str0ng-New-Passw0rd!',
        'password_confirmation' => 'Str0ng-New-Passw0rd!',
    ]);

    Notification::assertSentTo($user, PasswordChangedNotification::class);
});
