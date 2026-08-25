<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;

/**
 * MailInterface over the dedicated `brevo` SMTP mailer (smtp-relay.brevo.com).
 *
 * In PHPUnit (`MAIL_MAILER=array`) and local log mode the default mailer is
 * honored so tests never open a real SMTP connection.
 */
final readonly class BrevoMailAdapter implements MailInterface
{
    private const string MAILER = 'brevo';

    public function __construct(private MailFactory $mail) {}

    public function send(string|array $to, Mailable $mailable, string|array|null $bcc = null): void
    {
        $pending = $this->mail->mailer($this->resolveMailer())->to($to);

        if ($bcc !== null && $bcc !== [] && $bcc !== '') {
            $pending->bcc($bcc);
        }

        $pending->send($mailable);
    }

    public function queue(string|array $to, Mailable $mailable): void
    {
        $this->mail->mailer($this->resolveMailer())->to($to)->queue($mailable);
    }

    private function resolveMailer(): string
    {
        $default = (string) config('mail.default');

        return in_array($default, ['array', 'log'], true)
            ? $default
            : self::MAILER;
    }
}
