<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Infrastructure\Fetching\OutboundUrlGuard;
use Modules\LeadScout\Infrastructure\Fetching\RobotsTxtPolicy;

function guard(?callable $resolver = null): OutboundUrlGuard
{
    return new OutboundUrlGuard($resolver ?? static fn (string $host): array => ['93.184.216.34']);
}

it('allows public pages and rejects everything else', function (): void {
    $guard = guard();

    expect($guard->allows('https://agencia.example/servicios'))->toBeTrue()
        ->and($guard->allows('http://agencia.example/'))->toBeTrue()
        ->and($guard->allows('https://www.linkedin.com/company/acme'))->toBeFalse()
        ->and($guard->allows('https://apollo.io/x'))->toBeFalse()
        ->and($guard->allows('https://sortlist.com/agency/x'))->toBeFalse()
        ->and($guard->allows('ftp://agencia.example/file'))->toBeFalse()
        ->and($guard->allows('https://169.254.169.254/latest/meta-data'))->toBeFalse()
        ->and($guard->allows('https://10.0.0.5/admin'))->toBeFalse();
});

it('blocks private resolutions without a request', function (): void {
    $guard = new OutboundUrlGuard(static fn (string $host): array => ['192.168.1.10']);

    expect($guard->allows('https://intranet.example/'))->toBeFalse();

    Http::fake();
    $guard->allows('https://intranet.example/');
    Http::assertNothingSent();
});

it('parses groups, wildcards and dollar anchors', function (): void {
    $rules = RobotsTxtPolicy::parse(implode("\n", [
        'User-agent: *',
        'Disallow: /admin/',
        'Disallow: /*?sort=',
        'Allow: /admin/preview$',
        '',
        'User-agent: LeadScoutBot',
        'Disallow: /privado/',
    ]));

    $policy = new RobotsTxtPolicy('LeadScoutBot/1.0');

    // Structure only — behavior is asserted through isAllowed below.
    foreach ($rules as $rule) {
        expect($rule)->toHaveKeys(['agents', 'allow', 'pattern']);
    }

    expect($rules)->toHaveCount(4);
});

it('honors disallow, allow and dollar rules per agent', function (): void {
    Http::fake(['*' => Http::response(implode("\n", [
        'User-agent: *',
        'Disallow: /admin/',
        'Disallow: /*?sort=',
        'Allow: /admin/preview$',
    ]), 200)]);

    $policy = new RobotsTxtPolicy;

    expect($policy->isAllowed('https://agencia.example/servicios'))->toBeTrue()
        ->and($policy->isAllowed('https://agencia.example/admin/panel'))->toBeFalse()
        ->and($policy->isAllowed('https://agencia.example/admin/preview'))->toBeTrue()
        ->and($policy->isAllowed('https://agencia.example/blog?sort=asc'))->toBeFalse();
});

it('permits on robots failure instead of hanging the pipeline', function (): void {
    Http::fake(['*' => Http::response('boom', 500)]);

    expect((new RobotsTxtPolicy)->isAllowed('https://agencia.example/x'))->toBeTrue();
});
