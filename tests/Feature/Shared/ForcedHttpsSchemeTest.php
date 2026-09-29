<?php

declare(strict_types=1);

/**
 * Covers the PaaS deployment shape: the browser reaches the app over HTTPS while
 * PHP is reached over plain HTTP, so `$request->isSecure()` is false.
 *
 * The regression this locks down is visible in production, not in tests: on
 * Railway the rendered page asked the browser for its own Vite bundles over
 * `http://` — every font, stylesheet and script blocked as mixed content, so the
 * SPA came up unstyled and dead. `trustProxies(at: '*')` only helps while the
 * platform forwards X-Forwarded-Proto, so the scheme is derived from APP_URL.
 */

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\URL;
use Shared\Providers\SharedServiceProvider;

/**
 * `URL::forceScheme()` is sticky on the shared generator and the framework
 * exposes no reset, so the tests clear it by hand. Each test then boots the
 * provider with its own APP_URL, exactly like a fresh production process would.
 */
function bootSharedProvider(): void
{
    (new SharedServiceProvider(app()))->boot();
}

function clearForcedScheme(): void
{
    $generator = app(UrlGenerator::class);

    // forceScheme() memoises the root and the scheme, so all three go together.
    foreach (['forceScheme', 'cachedRoot', 'cachedScheme'] as $property) {
        (new ReflectionProperty(UrlGenerator::class, $property))->setValue($generator, null);
    }
}

beforeEach(function (): void {
    clearForcedScheme();
    config()->set('session.secure', null);
});

afterEach(function (): void {
    clearForcedScheme();
    config()->set('session.secure', null);
});

it('keeps http urls when app.url is http', function (): void {
    config()->set('app.url', 'http://localhost:8000');

    bootSharedProvider();

    expect(URL::to('/'))->toStartWith('http://');
});

it('forces https when app.url is https, so assets never fall back to http', function (): void {
    config()->set('app.url', 'https://argenis-hub-production.up.railway.app');

    bootSharedProvider();

    expect(URL::to('/'))->toStartWith('https://')
        ->and(asset('build/assets/app.js'))->toStartWith('https://')
        ->and(route('login'))->toStartWith('https://');
});

it('pins secure session cookies when app.url is https', function (): void {
    config()->set('app.url', 'https://argenis-hub-production.up.railway.app');

    bootSharedProvider();

    expect(config('session.secure'))->toBeTrue();
});

it('respects an explicit session.secure=false over the app.url scheme', function (): void {
    config()->set('app.url', 'https://argenis-hub-production.up.railway.app');
    config()->set('session.secure', false);

    bootSharedProvider();

    expect(config('session.secure'))->toBeFalse();
});

it('emits hsts when app.url is https even though php sees a plain http request', function (): void {
    config()->set('app.url', 'https://argenis-hub-production.up.railway.app');

    bootSharedProvider();

    $hsts = test()->get('/')->headers->get('Strict-Transport-Security');

    expect($hsts)->toContain('max-age=31536000')
        ->and($hsts)->not->toContain('includeSubDomains');
});

it('does not emit hsts for a plain http app.url', function (): void {
    config()->set('app.url', 'http://localhost:8000');

    bootSharedProvider();

    expect(test()->get('/')->headers->has('Strict-Transport-Security'))->toBeFalse();
});
