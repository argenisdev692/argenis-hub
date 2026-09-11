<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;

/**
 * MailInterface over the dedicated `resend` mailer — the Resend HTTPS API
 * (resend/resend-laravel), not an SMTP relay.
 *
 * Selected by `MAIL_ADAPTER=resend`; SharedServiceProvider resolves the binding,
 * so callers keep depending on {@see MailInterface} and never name a provider.
 * The API key lives in `services.resend.key` and the sender in
 * `mail.mailers.resend.from`.
 *
 * In PHPUnit (`MAIL_MAILER=array`) and local log mode the default mailer is
 * honored so tests never reach the Resend API.
 */
final readonly class ResendMailAdapter implements MailInterface
{
    use ResolvesOutboundMailer;

    private const string MAILER = 'resend';

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
