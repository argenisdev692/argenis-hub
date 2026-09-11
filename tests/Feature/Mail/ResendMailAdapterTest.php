<?php

declare(strict_types=1);

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Resend\Laravel\Transport\ResendTransportFactory;
use Shared\Infrastructure\Mail\BrevoMailAdapter;
use Shared\Infrastructure\Mail\MailInterface;
use Shared\Infrastructure\Mail\ResendMailAdapter;
use Shared\Infrastructure\Mail\UsesResendMailer;

/**
 * Resend is the second outbound transport beside Brevo. These assertions pin the
 * three things that silently break a provider swap: the mailer config the
 * transport is built from, the container binding MAIL_ADAPTER selects, and the
 * test escape hatch that keeps the suite off the real Resend API.
 */
final class ResendProbeMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('Probe')->html('<p>Probe</p>');
    }
}

test('the resend mailer is configured for the resend api transport', function (): void {
    expect(config('mail.mailers.resend.transport'))->toBe('resend');
});

test('the resend mailer builds a real resend transport from the api key', function (): void {
    config()->set('services.resend.key', 're_smoke_test');

    $transport = Mail::mailer('resend')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(ResendTransportFactory::class);
})->skip(
    fn (): bool => ! class_exists(ResendTransportFactory::class),
    'resend/resend-laravel is not installed.',
);

test('the resend api key is wired to services config', function (): void {
    config()->set('services.resend.key', 're_test_key');

    expect(config('services.resend.key'))->toBe('re_test_key');
});

test('the resend mailer sends from its own address, leaving brevo on the global one', function (): void {
    expect(config('mail.mailers.resend.from.address'))->not->toBeEmpty()
        ->and(config('mail.mailers.brevo.from'))->toBeNull();
});

test('the resend adapter targets the resend mailer outside of tests', function (): void {
    config()->set('mail.default', 'smtp');

    $factory = Mockery::mock(MailFactory::class);
    $factory->shouldReceive('mailer')->once()->with('resend')->andReturnSelf();
    $factory->shouldReceive('to')->once()->andReturnSelf();
    $factory->shouldReceive('send')->once();

    (new ResendMailAdapter($factory))->send('ana@example.com', new ResendProbeMailable);
});

test('the resend adapter honours the array mailer while testing', function (): void {
    config()->set('mail.default', 'array');

    $factory = Mockery::mock(MailFactory::class);
    $factory->shouldReceive('mailer')->once()->with('array')->andReturnSelf();
    $factory->shouldReceive('to')->once()->andReturnSelf();
    $factory->shouldReceive('queue')->once();

    (new ResendMailAdapter($factory))->queue('ana@example.com', new ResendProbeMailable);
});

test('the mail port resolves to the adapter named by MAIL_ADAPTER', function (string $adapter, string $expected): void {
    config()->set('mail.adapter', $adapter);

    expect(app(MailInterface::class))->toBeInstanceOf($expected);
})->with([
    'resend selects the resend adapter' => ['resend', ResendMailAdapter::class],
    'brevo stays the default' => ['brevo', BrevoMailAdapter::class],
    'an unknown value falls back to brevo' => ['typo', BrevoMailAdapter::class],
]);

test('the resend notification trait mirrors the adapter resolution', function (): void {
    $notification = new class
    {
        use UsesResendMailer;

        public function mailer(): string
        {
            return $this->resendMailer();
        }
    };

    config()->set('mail.default', 'smtp');
    expect($notification->mailer())->toBe('resend');

    config()->set('mail.default', 'array');
    expect($notification->mailer())->toBe('array');
});

test('mail dispatched through the resend adapter reaches the transport', function (): void {
    config()->set('mail.adapter', 'resend');
    Mail::fake();

    app(MailInterface::class)->send('ana@example.com', new ResendProbeMailable);

    Mail::assertSent(ResendProbeMailable::class);
});
