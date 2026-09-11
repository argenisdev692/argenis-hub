<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

/**
 * Notification-side counterpart of {@see ResendMailAdapter}: resolves the mailer
 * name for `MailMessage::mailer()` — Resend in production, `array`/`log` in tests.
 */
trait UsesResendMailer
{
    use ResolvesOutboundMailer;

    protected function resendMailer(): string
    {
        return $this->resolveOutboundMailer('resend');
    }
}
