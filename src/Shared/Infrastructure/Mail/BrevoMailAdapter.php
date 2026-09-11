<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;

/**
 * MailInterface over the dedicated `brevo` SMTP mailer (smtp-relay.brevo.com).
 *
 * The default binding (`MAIL_ADAPTER=brevo`); {@see ResendMailAdapter} is the
 * alternative provider. SharedServiceProvider resolves the binding, so callers
 * keep depending on {@see MailInterface} and never name a provider.
 *
 * In PHPUnit (`MAIL_MAILER=array`) and local log mode the default mailer is
 * honored so tests never open a real SMTP connection.
 */
final readonly class BrevoMailAdapter implements MailInterface
{
    use ResolvesOutboundMailer;

    private const string MAILER = 'brevo';

    public function __construct(private MailFactory $mail) {}

    public function send(string|array $to, Mailable $mailable, string|array|null $bcc = null): void
    {
        $pending = $this->mail->mailer($this->resolveOutboundMailer(self::MAILER))->to($to);

        if ($bcc !== null && $bcc !== [] && $bcc !== '') {
            $pending->bcc($bcc);
        }

        $pending->send($mailable);
    }

    public function queue(string|array $to, Mailable $mailable): void
    {
        $this->mail->mailer($this->resolveOutboundMailer(self::MAILER))->to($to)->queue($mailable);
    }
}
