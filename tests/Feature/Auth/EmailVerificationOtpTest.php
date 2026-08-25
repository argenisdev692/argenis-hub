<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Modules\Auth\Infrastructure\Notifications\AuthOneTimePasswordNotification;
use Spatie\OneTimePasswords\Models\OneTimePassword;

/**
 * Spec 001 US-02 / FR-02 — email verification by 6-digit code, never a link.
 */
beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

function issueVerificationCode(User $user): string
{
    test()->actingAs($user)->post(route('verification.send'));

    return (string) OneTimePassword::query()->latest('id')->value('password');
}

test('requesting verification sends a 6-digit code instead of a signed link', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'));

    Notification::assertSentTo($user, AuthOneTimePasswordNotification::class);
});

test('the issued code is six numeric digits and expires in thirty minutes', function (): void {
    $user = User::factory()->unverified()->create();

    issueVerificationCode($user);
    $code = OneTimePassword::query()->latest('id')->firstOrFail();

    expect($code->password)->toMatch('/^\d{6}$/');
    expect($code->expires_at->diffInMinutes(now(), absolute: true))->toBeGreaterThan(25);
});

test('a valid code verifies the address', function (): void {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create();
    $code = issueVerificationCode($user);

    $this->actingAs($user)
        ->post(route('auth.email.verify-code'), ['code' => $code])
        ->assertSessionHasNoErrors();

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a wrong code is rejected', function (): void {
    $user = User::factory()->unverified()->create();
    issueVerificationCode($user);

    $this->actingAs($user)
        ->post(route('auth.email.verify-code'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an expired code is rejected', function (): void {
    $user = User::factory()->unverified()->create();
    $code = issueVerificationCode($user);

    OneTimePassword::query()->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($user)
        ->post(route('auth.email.verify-code'), ['code' => $code])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a code cannot be used twice', function (): void {
    $user = User::factory()->unverified()->create();
    $code = issueVerificationCode($user);

    $this->actingAs($user)->post(route('auth.email.verify-code'), ['code' => $code]);

    $second = User::factory()->unverified()->create();

    $this->actingAs($second)
        ->post(route('auth.email.verify-code'), ['code' => $code])
        ->assertSessionHasErrors('code');

    expect($second->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an unverified user cannot reach protected pages', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('the code endpoint rejects malformed input', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('auth.email.verify-code'), ['code' => 'abcdef'])
        ->assertSessionHasErrors('code');
});

test('the code endpoint is closed to guests', function (): void {
    $this->post(route('auth.email.verify-code'), ['code' => '123456'])
        ->assertRedirect(route('login'));
});
