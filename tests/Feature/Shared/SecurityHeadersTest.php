<?php

declare(strict_types=1);
use Illuminate\Testing\TestResponse;

/**
 * Guards the response headers emitted by Shared\Infrastructure\Http\Middleware\SecurityHeaders.
 *
 * The regression these lock down is a silent one: the middleware existed and was
 * fully documented, but nothing ever appended it to the middleware stack, so the
 * application served every page with no Content-Security-Policy at all. Each test
 * below therefore asserts the header is present, not just well-formed.
 */
function securityHeadersFrom(string $uri): TestResponse
{
    return test()->get($uri);
}

it('emits a content security policy on web responses', function (): void {
    $response = securityHeadersFrom('/');

    $response->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toBeString()->not->toBeEmpty();
});

it('upgrades insecure requests so blocked mixed content is fetched over https', function (): void {
    $policy = securityHeadersFrom('/')->headers->get('Content-Security-Policy');

    // Blockable mixed content (fonts, stylesheets, scripts) is refused outright
    // without this directive; `block-all-mixed-content` is obsolete and must not
    // appear in its place.
    expect($policy)->toContain('upgrade-insecure-requests')
        ->not->toContain('block-all-mixed-content');
});

it('keeps the policy locked down on the directives that matter', function (): void {
    $policy = securityHeadersFrom('/')->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("base-uri 'self'");
});

it('binds scripts to the per request nonce instead of unsafe-inline', function (): void {
    $policy = securityHeadersFrom('/')->headers->get('Content-Security-Policy');

    // Only script-src may not carry unsafe-inline. Vue and Volt inject runtime
    // style tags and style="" attributes, so style-src-attr legitimately does.
    preg_match('/script-src ([^;]*)/', (string) $policy, $scriptSrc);

    $sources = $scriptSrc[1] ?? '';

    expect($sources)->toStartWith("'self' 'nonce-")
        ->and($sources)->not->toContain('unsafe-inline');
});

it('carries the transport hardening headers', function (): void {
    $response = securityHeadersFrom('/');

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toBe(config('security.headers.permissions_policy'));
});

it('does not pin plain http local development with hsts', function (): void {
    // The middleware only emits HSTS on secure requests, so a http:// dev server
    // stays usable while the production hostname is pinned.
    expect(securityHeadersFrom('/')->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('opens connect-src for the r2 bucket the s3 sdk signs against', function (): void {
    config()->set('filesystems.disks.r2.endpoint', 'https://acct123.r2.cloudflarestorage.com');
    config()->set('filesystems.disks.r2.bucket', 'media');
    config()->set('filesystems.disks.r2.use_path_style_endpoint', false);

    $policy = securityHeadersFrom('/')->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain('connect-src')
        ->toContain('https://media.acct123.r2.cloudflarestorage.com')
        ->toContain('img-src')
        ->not->toContain('*.r2.cloudflarestorage.com');
});

it('still emits the policy for json api responses', function (): void {
    $response = test()->getJson('/api/does-not-exist');

    expect($response->headers->get('Content-Security-Policy'))->toContain('upgrade-insecure-requests');
});
