<?php

declare(strict_types=1);

use App\Models\CompanyData;
use App\Models\User;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * `GET /api/public/company` — the payload the external Astro landing sites read.
 *
 * This is the only unauthenticated route in the application, so the leakage
 * tests below are the load-bearing ones: the same row holds the fiscal identity,
 * the bank block and the owner id, and none of it may cross this boundary. The
 * allowlist in `PublicCompanyData` is what keeps that true, including for
 * columns added after this test was written.
 */
beforeEach(function (): void {
    CompanyProfile::forget();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function publishedCompany(array $attributes = []): CompanyData
{
    return CompanyData::factory()->withSocials()->create([
        'user_id' => User::factory(),
        ...$attributes,
    ]);
}

it('is reachable without authentication', function (): void {
    publishedCompany();

    $this->getJson(route('api.public.company'))->assertOk();
});

it('answers 404 before the installation has been seeded', function (): void {
    $this->getJson(route('api.public.company'))->assertNotFound();
});

it('returns the trading name, marks, socials and address', function (): void {
    publishedCompany([
        'company_name' => 'Argenis Hub',
        'name' => 'Argenis Carrillo Gonzalez',
        'description' => 'An AI-powered CRM.',
        'website' => 'https://argenis.dev',
        'email' => 'info@argenis.dev',
        'phone' => '+351 963 490 414',
        'address' => 'Rua da Saudade, No 1',
        'address_2' => 'R/C Esq.',
        'zip_code' => '6200-386',
        'city' => 'Covilha',
        'state' => 'Castelo Branco',
        'country' => 'Portugal',
        'country_code' => 'PT',
        'latitude' => 40.2806,
        'longitude' => -7.5049,
    ]);

    $this->getJson(route('api.public.company'))
        ->assertOk()
        ->assertJsonStructure([
            'name', 'legal_name', 'description', 'website', 'email', 'phone',
            'logos' => ['logo', 'logo_white', 'mark'],
            'socials' => ['facebook', 'github', 'instagram', 'linkedin', 'tiktok', 'twitter'],
            'address' => [
                'line_1', 'line_2', 'zip_code', 'city', 'state',
                'country', 'country_code', 'latitude', 'longitude', 'formatted',
            ],
        ])
        ->assertJsonPath('name', 'Argenis Hub')
        ->assertJsonPath('legal_name', 'Argenis Carrillo Gonzalez')
        ->assertJsonPath('address.line_2', 'R/C Esq.')
        ->assertJsonPath('address.latitude', 40.2806)
        ->assertJsonPath(
            'address.formatted',
            'Rua da Saudade, No 1, R/C Esq., 6200-386 Covilha, Castelo Branco, Portugal',
        );
});

it('composes the address line without dangling separators when parts are missing', function (): void {
    publishedCompany([
        'address' => 'Rua da Saudade, No 1',
        'address_2' => null,
        'zip_code' => null,
        'city' => 'Covilha',
        'state' => null,
        'country' => 'Portugal',
    ]);

    $this->getJson(route('api.public.company'))
        ->assertOk()
        ->assertJsonPath('address.formatted', 'Rua da Saudade, No 1, Covilha, Portugal');
});

it('lists every social channel, present or not', function (): void {
    publishedCompany([
        'linkedin_link' => 'https://www.linkedin.com/in/argenisdev692/',
        'twitter_link' => null,
    ]);

    $this->getJson(route('api.public.company'))
        ->assertOk()
        ->assertJsonPath('socials.linkedin', 'https://www.linkedin.com/in/argenisdev692/')
        ->assertJsonPath('socials.twitter', null);
});

it('never leaks the fiscal, banking or ownership columns', function (): void {
    publishedCompany([
        'nif_nipc' => '316416584',
        'nie' => '2175V64V7',
        'bank_beneficiary' => 'Argenis Jose Carrillo Gonzalez',
        'bank_iban' => 'PT50003600119910006305349',
        'bank_bic' => 'MPIOPTPL',
        'bank_name' => 'Montepio',
        'invoice_notes' => 'Reverse charge.',
        'signature_path' => 'company/signature.png',
    ]);

    $body = $this->getJson(route('api.public.company'))->assertOk()->getContent() ?: '';

    expect($body)
        ->not->toContain('316416584')
        ->not->toContain('2175V64V7')
        ->not->toContain('PT50003600119910006305349')
        ->not->toContain('MPIOPTPL')
        ->not->toContain('Montepio')
        ->not->toContain('Reverse charge.')
        ->not->toContain('signature')
        ->not->toContain('user_id')
        ->not->toContain('uuid');
});

it('serves the brand marks as absolute URLs', function (): void {
    publishedCompany(['logo_path' => null]);

    $this->getJson(route('api.public.company'))
        ->assertOk()
        ->assertJsonPath('logos.logo', rtrim((string) config('app.url'), '/').'/img/Logo.png');
});

it('serves later callers from the cache', function (): void {
    $model = publishedCompany(['company_name' => 'Before']);

    $this->getJson(route('api.public.company'))->assertJsonPath('name', 'Before');

    // Straight to the database, so no model event fires and no cache is flushed.
    CompanyData::query()->whereKey($model->getKey())->update(['company_name' => 'After']);

    $this->getJson(route('api.public.company'))->assertJsonPath('name', 'Before');
});

it('flushes the cache the moment the record is saved', function (): void {
    $model = publishedCompany(['company_name' => 'Before']);

    $this->getJson(route('api.public.company'))->assertJsonPath('name', 'Before');

    $model->update(['company_name' => 'After']);

    $this->getJson(route('api.public.company'))->assertJsonPath('name', 'After');
});

it('throttles an unauthenticated caller', function (): void {
    publishedCompany();

    foreach (range(1, 60) as $ignored) {
        $this->getJson(route('api.public.company'))->assertOk();
    }

    $this->getJson(route('api.public.company'))->assertStatus(429);
});
