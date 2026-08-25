<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

/**
 * Resolves the outbound Laravel mailer name the same way
 * {@see BrevoMailAdapter} does — Brevo in production, honor `array`/`log` in tests.
 */
trait UsesBrevoMailer
{
    protected function brevoMailer(): string
    {
        $default = (string) config('mail.default');

        return in_array($default, ['array', 'log'], true)
            ? $default
            : 'brevo';
    }
}
