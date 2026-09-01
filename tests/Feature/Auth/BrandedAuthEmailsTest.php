<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Auth\Infrastructure\Notifications\AuthOneTimePasswordNotification;
use Modules\Auth\Infrastructure\Notifications\NewDeviceDetectedNotification;
use Modules\Auth\Infrastructure\Notifications\PasswordChangedNotification;

/**
 * The three auth emails must render through the branded `emails.layout`
 * (company logo + brand palette) and leave over the Brevo relay.
 *
 * These assertions exist because the rest of the suite fakes notifications,
 * which means `toMail()` never executes: a broken template or a missing view
 * variable would otherwise ship silently.
 */
test('the new-device email renders the branded layout with its payload', function (): void {
    $notification = new NewDeviceDetectedNotification(
        ipAddress: '203.0.113.44',
        userAgent: 'Mozilla/5.0 (Macintosh)',
        occurredAt: 'Mon, Aug 25, 2026 9:15 AM',
    );

    $html = (string) $notification->toMail(User::factory()->create())->render();

    expect($html)
        ->toContain('203.0.113.44')
        ->toContain('Mozilla/5.0 (Macintosh)')
        ->toContain('Mon, Aug 25, 2026 9:15 AM')
        ->toContain(route('auth.sessions.index'))
        ->toContain('img/logo-argenis-hub-white.png')
        ->toContain('#7c3aed');
});

test('the password-changed email renders the branded layout with its payload', function (): void {
    $notification = new PasswordChangedNotification(
        ipAddress: '198.51.100.7',
        changedAt: 'Mon, Aug 25, 2026 9:20 AM',
    );

    $html = (string) $notification->toMail(User::factory()->create())->render();

    expect($html)
        ->toContain('198.51.100.7')
        ->toContain('Mon, Aug 25, 2026 9:20 AM')
        ->toContain('img/logo-argenis-hub-white.png')
        ->toContain('#7c3aed');
});

test('the one-time-password email renders the code inside the branded layout', function (): void {
    $user = User::factory()->create();
    $oneTimePassword = $user->createOneTimePassword();

    $html = (string) (new AuthOneTimePasswordNotification($oneTimePassword))
        ->toMail($user)
        ->render();

    expect($html)
        ->toContain($oneTimePassword->password)
        ->toContain('img/logo-argenis-hub-white.png')
        ->toContain('#7c3aed');
});

test('auth emails are addressed to the brevo mailer outside of tests', function (): void {
    config()->set('mail.default', 'smtp');

    $message = (new PasswordChangedNotification)->toMail(User::factory()->create());

    expect($message->mailer)->toBe('brevo');
});

test('auth emails honour the array mailer while testing', function (): void {
    $message = (new PasswordChangedNotification)->toMail(User::factory()->create());

    expect($message->mailer)->toBe('array');
});
