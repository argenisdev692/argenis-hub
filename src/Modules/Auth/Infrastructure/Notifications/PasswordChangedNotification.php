<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Shared\Infrastructure\Mail\UsesBrevoMailer;

/**
 * Confirms a password change to the account owner (FR-13) — the out-of-band
 * signal that makes a silent account takeover visible.
 *
 * The originating IP and the moment of the change are captured by the caller
 * and passed in: resolving them here would read the queue worker's request,
 * not the one that actually changed the credential.
 *
 * Rendered by the branded `emails.security.password-changed` view and delivered
 * over the Brevo relay.
 */
final class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesBrevoMailer;

    public function __construct(
        private readonly ?string $ipAddress = null,
        private readonly ?string $changedAt = null,
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
        return (new MailMessage)
            ->mailer($this->brevoMailer())
            ->subject(__('Your :app password was changed', ['app' => config('app.name')]))
            ->view('emails.security.password-changed', [
                'ipAddress' => $this->ipAddress,
                'changedAt' => $this->changedAt,
            ]);
    }
}
