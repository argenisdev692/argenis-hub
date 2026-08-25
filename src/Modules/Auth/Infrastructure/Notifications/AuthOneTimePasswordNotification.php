<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Shared\Infrastructure\Mail\UsesBrevoMailer;
use Spatie\OneTimePasswords\Notifications\OneTimePasswordNotification;

/**
 * Carries the 6-digit code used for email verification (FR-02) and password
 * reset (FR-11).
 *
 * One notification for both flows on purpose: the message is identical from the
 * recipient's point of view — "here is your code, it expires in N minutes" — and
 * splitting it would duplicate the template for no behavioural difference.
 *
 * Rendered by the branded `emails.security.one-time-password` view (company
 * logo + brand palette) and delivered over the Brevo relay. Queued so mail
 * delivery never sits inside the auth request (NFR-02).
 */
final class AuthOneTimePasswordNotification extends OneTimePasswordNotification implements ShouldQueue
{
    use UsesBrevoMailer;

    public function toMail(object $notifiable): MailMessage
    {
        $expiresInMinutes = (int) $this->oneTimePassword->expires_at->diffInMinutes(now(), absolute: true);

        return (new MailMessage)
            ->mailer($this->brevoMailer())
            ->subject(__('Your :app verification code', ['app' => config('app.name')]))
            ->view('emails.security.one-time-password', [
                'code' => $this->oneTimePassword->password,
                'minutes' => max(1, $expiresInMinutes),
            ]);
    }
}
