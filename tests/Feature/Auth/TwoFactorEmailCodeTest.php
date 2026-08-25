<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Features;
use Modules\Auth\Infrastructure\Notifications\AuthOneTimePasswordNotification;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

/**
 * Clears the first factor: posts valid credentials so Fortify parks the user
 * on the two-factor challenge and writes `login.id` to the session.
 */
function challengeWith(User $user, bool $remember = false): void
{
    test()->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => $remember,
    ]);
}

test('a challenged user can have a code emailed to them', function () {
    Notification::fake();

    $user = User::factory()->withTwoFactor()->create();
    challengeWith($user);

    $this->from(route('two-factor.login'))
        ->post(route('auth.two-factor.email-code.send'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('status');

    Notification::assertSentTo($user, AuthOneTimePasswordNotification::class);
});

test('a guest with no challenge in the session cannot request a code', function () {
    Notification::fake();

    $this->post(route('auth.two-factor.email-code.send'))
        ->assertRedirect(route('login'));

    Notification::assertNothingSent();
});

test('a valid emailed code completes the login', function () {
    Event::fake([ValidTwoFactorAuthenticationCodeProvided::class]);

    $user = User::factory()->withTwoFactor()->create();
    challengeWith($user);

    $code = $user->createOneTimePassword()->password;

    $this->post(route('auth.two-factor.email-code.verify'), ['code' => $code])
        ->assertRedirect(config('fortify.home'));

    assertAuthenticatedAs($user);
    Event::assertDispatched(ValidTwoFactorAuthenticationCodeProvided::class);
});

test('the challenge session is cleared once the code is redeemed', function () {
    $user = User::factory()->withTwoFactor()->create();
    challengeWith($user);

    $code = $user->createOneTimePassword()->password;

    $this->post(route('auth.two-factor.email-code.verify'), ['code' => $code]);

    expect(session()->has('login.id'))->toBeFalse()
        ->and(session()->has('login.remember'))->toBeFalse();
});

test('a wrong code leaves the user unauthenticated', function () {
    Event::fake([TwoFactorAuthenticationFailed::class]);

    $user = User::factory()->withTwoFactor()->create();
    challengeWith($user);

    $user->createOneTimePassword();

    $this->from(route('two-factor.login'))
        ->post(route('auth.two-factor.email-code.verify'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    assertGuest();
    Event::assertDispatched(TwoFactorAuthenticationFailed::class);
});

test('a code cannot be redeemed twice', function () {
    $user = User::factory()->withTwoFactor()->create();
    challengeWith($user);

    $code = $user->createOneTimePassword()->password;

    $this->post(route('auth.two-factor.email-code.verify'), ['code' => $code]);
    assertAuthenticatedAs($user);

    $this->post(route('logout'));
    assertGuest();

    challengeWith($user);

    $this->from(route('two-factor.login'))
        ->post(route('auth.two-factor.email-code.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');

    assertGuest();
});

test('the code is rejected when it is not six digits', function () {
    $user = User::factory()->withTwoFactor()->create();
    challengeWith($user);

    $this->from(route('two-factor.login'))
        ->post(route('auth.two-factor.email-code.verify'), ['code' => '123'])
        ->assertSessionHasErrors('code');

    assertGuest();
});

test('an account without two factor enrolled cannot use the emailed code path', function () {
    Notification::fake();

    // A user with no `two_factor_confirmed_at` never reaches the challenge, so
    // planting `login.id` by hand is the only way to probe this endpoint.
    $user = User::factory()->create();

    $this->withSession(['login.id' => $user->getKey()])
        ->post(route('auth.two-factor.email-code.send'))
        ->assertRedirect(route('login'));

    Notification::assertNothingSent();
});
