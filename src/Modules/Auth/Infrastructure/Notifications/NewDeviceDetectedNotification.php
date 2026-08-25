<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Shared\Infrastructure\Mail\UsesBrevoMailer;

/**
 * Alerts the account owner about a sign-in from a device they have not used
 * before (FR-15), and points them at the page where they can end it.
 *
 * Rendered by the branded `emails.security.new-device` view and delivered over
 * the Brevo relay.
 */
final class NewDeviceDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesBrevoMailer;

    public function __construct(
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly string $occurredAt,
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
            ->subject(__('New sign-in to your :app account', ['app' => config('app.name')]))
            ->view('emails.security.new-device', [
                'ipAddress' => $this->ipAddress,
                'userAgent' => $this->userAgent,
                'occurredAt' => $this->occurredAt,
                'sessionsUrl' => route('auth.sessions.index'),
            ]);
    }
}
