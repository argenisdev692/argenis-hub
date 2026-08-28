<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Modules\ContactSupport\Domain\Events\ContactSupportSubmitted;
use Modules\ContactSupport\Infrastructure\Notifications\ContactSupportReceivedNotification;
use Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models\ContactSupportEloquentModel;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * Emails the company inbox when a new public contact request lands.
 *
 * Queued: the alert must never delay the public submission response. A row
 * SpamGuard flagged is skipped unless `notifications.include_spam` is on — the
 * flagged submission is still visible in the admin "Spam" folder.
 */
final readonly class SendContactSupportReceivedNotificationListener implements ShouldQueue
{
    public function handle(ContactSupportSubmitted $event): void
    {
        if (config('contact-support.notifications.enabled') !== true) {
            return;
        }

        if ($event->isSpam && config('contact-support.notifications.include_spam') !== true) {
            return;
        }

        $support = ContactSupportEloquentModel::query()->where('uuid', $event->uuid)->first();

        if ($support === null) {
            return;
        }

        $recipient = $this->recipient();

        if ($recipient === null || $recipient === '') {
            return;
        }

        /** @var list<string> $spamReasons */
        $spamReasons = $support->spam_reasons ?? [];

        Notification::route('mail', $recipient)->notify(new ContactSupportReceivedNotification(
            uuid: $support->uuid,
            firstName: $support->first_name,
            lastName: $support->last_name,
            submitterEmail: $support->email,
            phone: $support->phone,
            subject: $support->subject,
            message: $support->message,
            smsConsent: $support->sms_consent,
            isSpam: $support->is_spam,
            spamScore: $support->spam_score,
            spamReasons: $spamReasons,
            submittedAt: $support->created_at?->toDayDateTimeString() ?? '',
        ));
    }

    private function recipient(): ?string
    {
        $override = config('contact-support.notifications.recipient');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return CompanyProfile::data()['support_email'];
    }
}
