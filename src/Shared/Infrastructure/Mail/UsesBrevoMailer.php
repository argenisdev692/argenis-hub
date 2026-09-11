<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

/**
 * Notification-side counterpart of {@see BrevoMailAdapter}: resolves the mailer
 * name for `MailMessage::mailer()` — Brevo in production, `array`/`log` in tests.
 */
trait UsesBrevoMailer
{
    use ResolvesOutboundMailer;

    protected function brevoMailer(): string
    {
        return $this->resolveOutboundMailer('brevo');
    }
}
