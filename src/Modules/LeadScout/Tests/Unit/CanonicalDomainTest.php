<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;

it('strips www, case, path and query', function (): void {
    $domain = CanonicalDomain::fromUrl('https://WWW.Acme.PT/careers?ref=1');

    expect($domain->value)->toBe('acme.pt');
});

it('preserves non-www subdomains such as careers', function (): void {
    $domain = CanonicalDomain::fromUrl('https://careers.acme.pt/jobs');

    expect($domain->value)->toBe('careers.acme.pt');
});

it('converts IDN to punycode', function (): void {
    $domain = CanonicalDomain::fromUrl('https://müller-muñoz.es/servicios');

    expect($domain->value)->toBe('xn--mller-muoz-09a8j.es');
});

it('compares by value', function (): void {
    expect(CanonicalDomain::fromUrl('https://acme.pt')->equals(CanonicalDomain::fromUrl('http://www.ACME.pt/')))
        ->toBeTrue();
});

it('rejects hosts that are not valid domains', function (): void {
    expect(fn (): CanonicalDomain => CanonicalDomain::fromUrl('not a url at all'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): CanonicalDomain => new CanonicalDomain(''))
        ->toThrow(InvalidArgumentException::class);
});
