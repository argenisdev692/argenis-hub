<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Modules\Auth\Infrastructure\Notifications\AuthOneTimePasswordNotification;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

// Spec 001 FR-02 replaced the signed verification link with a 6-digit code, so
// this route now delivers AuthOneTimePasswordNotification instead of VerifyEmail.
test('sends verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, AuthOneTimePasswordNotification::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    // Scoped to the verification notification on purpose: the request is also a
    // first sighting of this device, so FR-15's NewDeviceDetectedNotification is
    // expected here and must not fail the assertion.
    Notification::assertNotSentTo($user, AuthOneTimePasswordNotification::class);
});
