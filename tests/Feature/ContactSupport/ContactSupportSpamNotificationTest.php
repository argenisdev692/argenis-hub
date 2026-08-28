<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Modules\ContactSupport\Infrastructure\Notifications\ContactSupportReceivedNotification;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;

/**
 * The public contact form's second defence layer: SpamGuard scores the content
 * once it is past the honeypot + per-IP throttle, and a genuine request emails
 * the company inbox off the request path.
 */

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactSubmission(array $overrides = []): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+14155552671',
        'subject' => 'Landing page enquiry',
        'message' => 'Please send me a quote for a one-page marketing site.',
        'sms_consent' => true,
        ...$overrides,
    ];
}

beforeEach(function (): void {
    config(['contact-support.notifications.recipient' => 'inbox@company.test']);
});

it('emails the company inbox when a genuine request arrives', function (): void {
    Notification::fake();

    $this->postJson(route('api.public.contact-supports'), contactSubmission())
        ->assertCreated();

    $support = ContactSupportEloquentModel::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($support->is_spam)->toBeFalse()
        ->and($support->spam_score)->toBe(0)
        ->and($support->spam_reasons)->toBeNull();

    Notification::assertSentOnDemand(
        ContactSupportReceivedNotification::class,
        function (ContactSupportReceivedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notifiable->routeNotificationFor('mail') === 'inbox@company.test'
                && $channels === ['mail'];
        },
    );
});

it('flags a link flood as spam and does not email the inbox', function (): void {
    Notification::fake();

    $this->postJson(route('api.public.contact-supports'), contactSubmission([
        'email' => 'promo@spammer.test',
        'message' => 'Deals here https://a.example http://b.example www.c.example https://d.example buy now.',
    ]))->assertCreated();

    $support = ContactSupportEloquentModel::query()->where('email', 'promo@spammer.test')->firstOrFail();

    expect($support->is_spam)->toBeTrue()
        ->and($support->spam_score)->toBeGreaterThanOrEqual(60)
        ->and($support->spam_reasons)->toContain('too_many_links');

    Notification::assertNothingSent();
});

it('still emails a flagged request when include_spam is enabled', function (): void {
    Notification::fake();
    config(['contact-support.notifications.include_spam' => true]);

    $this->postJson(route('api.public.contact-supports'), contactSubmission([
        'email' => 'promo@spammer.test',
        'message' => 'Deals here https://a.example http://b.example www.c.example https://d.example buy now.',
    ]))->assertCreated();

    Notification::assertSentOnDemand(ContactSupportReceivedNotification::class);
});

it('sends nothing when operator notifications are disabled', function (): void {
    Notification::fake();
    config(['contact-support.notifications.enabled' => false]);

    $this->postJson(route('api.public.contact-supports'), contactSubmission())
        ->assertCreated();

    Notification::assertNothingSent();
});

it('routes to the company profile inbox when no recipient override is set', function (): void {
    Notification::fake();
    config(['contact-support.notifications.recipient' => null]);

    $this->postJson(route('api.public.contact-supports'), contactSubmission())
        ->assertCreated();

    Notification::assertSentOnDemand(
        ContactSupportReceivedNotification::class,
        function (ContactSupportReceivedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            $address = $notifiable->routeNotificationFor('mail');

            return is_string($address) && $address !== '';
        },
    );
});
