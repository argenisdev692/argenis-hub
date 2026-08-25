<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Mailable;

/**
 * Cross-cutting outbound mail contract. Production binding is
 * {@see BrevoMailAdapter} (Brevo SMTP relay). Callers never import a concrete
 * transport — provider switches happen only in SharedServiceProvider + config.
 */
interface MailInterface
{
    /**
     * Deliver immediately via the configured Brevo mailer.
     * Prefer calling this from a queued listener (`ShouldQueue` + `$queue = 'emails'`).
     *
     * @param  list<string>|string  $to
     * @param  list<string>|string|null  $bcc
     */
    public function send(string|array $to, Mailable $mailable, string|array|null $bcc = null): void;

    /**
     * Push the mailable onto the queue via the Brevo mailer.
     *
     * @param  list<string>|string  $to
     */
    public function queue(string|array $to, Mailable $mailable): void;
}
