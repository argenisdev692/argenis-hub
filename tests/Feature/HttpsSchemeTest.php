<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

test('proxied https request generates https asset urls', function (): void {
    $response = $this->call(
        'GET',
        '/',
        [],
        [],
        [],
        ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10']
    );

    $response->assertOk();

    $html = (string) $response->getContent();

    // Railway forwards plain HTTP; with trustProxies the forwarded proto
    // must win so @vite emits https:// URLs (no mixed-content white page).
    expect($html)->not->toContain('http://localhost/build')
        ->and($html)->toContain('https://');
});

test('production forces https scheme for generated urls', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    try {
        (new AppServiceProvider(app()))->boot();

        expect(URL::to('/build/assets/app.js'))->toStartWith('https://');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
        URL::forceScheme(null);
    }
});
