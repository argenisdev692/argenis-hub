<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Shared\Infrastructure\Http\Csp\GoogleMapsPreset;
use Spatie\Csp\Policy;

/*
|--------------------------------------------------------------------------
| Form kit — server-side foundations
|--------------------------------------------------------------------------
|
| The kit itself is Vue, but three server pieces have to hold for the Places
| address field to work at all: the browser key must reach the page, the CSP
| must permit the Maps origins, and the playground route must stay out of
| production. Each is covered below.
|
*/

it('exposes the google maps browser key to every inertia page', function (): void {
    config()->set('services.google_maps.key', 'test-browser-key');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('googleMapsKey', 'test-browser-key')
        );
});

it('shares a null maps key when none is configured, so the field degrades instead of breaking', function (): void {
    config()->set('services.google_maps.key', null);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('googleMapsKey', null)
        );
});

it('reads the maps key from the GOOGLE_MAPS_API_KEY environment variable', function (): void {
    expect(config('services.google_maps.key'))->toBe(env('GOOGLE_MAPS_API_KEY'));
});

it('allows the maps origins the places autocomplete needs', function (): void {
    $policy = new Policy;

    (new GoogleMapsPreset)->configure($policy);

    $header = $policy->getContents();

    expect($header)
        ->toContain('script-src')
        ->toContain('https://maps.googleapis.com')
        ->toContain('connect-src')
        ->toContain('https://places.googleapis.com');
});

it('does not weaken style-src, which the gmp-place-autocomplete element would have required', function (): void {
    $policy = new Policy;

    (new GoogleMapsPreset)->configure($policy);

    expect($policy->getContents())->not->toContain("style-src 'unsafe-inline'");
});

it('registers the maps preset alongside the basic policy', function (): void {
    expect(config('csp.presets'))->toContain(GoogleMapsPreset::class);
});

it('keeps the form-kit playground out of non-local environments', function (): void {
    // The route file guards on the environment at registration time, so the
    // presence of the named route is the assertion.
    expect(app()->environment())->not->toBe('production');

    if (app()->environment('local')) {
        expect(Route::has('design.form-kit'))->toBeTrue();

        return;
    }

    expect(Route::has('design.form-kit'))->toBeFalse();
});
