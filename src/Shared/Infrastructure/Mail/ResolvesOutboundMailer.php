<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Mail;

/**
 * Single source of truth for "which Laravel mailer does outbound mail leave by".
 *
 * Every provider adapter and notification trait in this namespace routes through
 * here so the test escape hatch is written once: under PHPUnit (`MAIL_MAILER=array`)
 * and local log mode the default mailer wins, so no test ever opens a real SMTP
 * connection or spends a provider API call.
 */
trait ResolvesOutboundMailer
{
    /** @var list<string> Mailers that must never be overridden by a provider. */
    private const array PASSTHROUGH_MAILERS = ['array', 'log'];

    /**
     * @param  string  $provider  Mailer name configured under `mail.mailers.*`.
     */
    protected function resolveOutboundMailer(string $provider): string
    {
        $default = (string) config('mail.default');

        return in_array($default, self::PASSTHROUGH_MAILERS, true)
            ? $default
            : $provider;
    }
}
