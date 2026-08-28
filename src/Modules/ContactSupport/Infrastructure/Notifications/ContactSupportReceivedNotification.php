<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Shared\Infrastructure\Mail\UsesBrevoMailer;

/**
 * Tells the company inbox a new landing-page contact request has arrived.
 *
 * Rendered by the branded `emails.contact-support.received` view and delivered
 * over the Brevo relay. Queued: the public submission response must never wait
 * on SMTP. `replyTo` is set to the visitor so an operator can answer straight
 * from the notification.
 */
final class ContactSupportReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesBrevoMailer;

    /**
     * @param  list<string>  $spamReasons
     */
    public function __construct(
        private readonly string $uuid,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly string $submitterEmail,
        private readonly string $phone,
        private readonly string $subject,
        private readonly string $message,
        private readonly bool $smsConsent,
        private readonly bool $isSpam,
        private readonly int $spamScore,
        private readonly array $spamReasons,
        private readonly string $submittedAt,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($this->firstName.' '.$this->lastName);

        return (new MailMessage)
            ->mailer($this->brevoMailer())
            ->subject(__('New contact request: :subject', ['subject' => $this->subject]))
            ->replyTo($this->submitterEmail, $name !== '' ? $name : null)
            ->view('emails.contact-support.received', [
                'name' => $name,
                'submitterEmail' => $this->submitterEmail,
                'phone' => $this->phone,
                'subject' => $this->subject,
                'messageBody' => $this->message,
                'smsConsent' => $this->smsConsent,
                'isSpam' => $this->isSpam,
                'spamScore' => $this->spamScore,
                'spamReasons' => $this->spamReasons,
                'submittedAt' => $this->submittedAt,
                'inboxUrl' => route('contact-supports.index'),
            ]);
    }
}
