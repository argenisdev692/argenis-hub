<?php

declare(strict_types=1);

use App\Models\CompanyData;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * The authenticated company settings surface.
 *
 * The record is a singleton, so every assertion here is about the one row: that
 * it can be read and edited by an operator holding the right permission, that it
 * cannot be touched by anyone else, and that values arrive normalized rather
 * than exactly as typed.
 */
beforeEach(function (): void {
    CompanyProfile::forget();
    $this->seed(RolePermissionSeeder::class);

    // The Vite manifest is a build artefact, so the tests must not depend on
    // someone having run `npm run build` first.
    //
    // `ensure_pages_exist` stays ON, unlike the manifest lookup: the Vue page
    // components now exist, and the component name is precisely the contract
    // between this controller and the frontend module. Leaving the check
    // disabled would let `Inertia::render('settings/company/Show')` be renamed
    // — or the page file moved — with a green suite and a blank screen.
    $this->withoutVite();
});

/**
 * @param  list<string>  $permissions
 */
function operator(array $permissions = ['VIEW_COMPANY_DATA', 'UPDATE_COMPANY_DATA']): User
{
    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function company(array $attributes = []): CompanyData
{
    return CompanyData::factory()->create([
        'user_id' => User::factory(),
        ...$attributes,
    ]);
}

/**
 * The full valid payload. Individual tests override one key at a time so a
 * failure names the field that broke rather than the whole form.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function companyPayload(array $overrides = []): array
{
    return [
        'company_name' => 'Argenis Hub',
        'legal_name' => 'Argenis Carrillo Gonzalez',
        'description' => "First line.\nSecond line.",
        'website' => 'https://argenis.dev',
        'email' => 'info@argenis.dev',
        'phone' => '+351 963 490 414',
        'address' => 'Rua da Saudade, No 1',
        'address_2' => 'R/C Esq.',
        'zip_code' => '6200-386',
        'city' => 'Covilha',
        'state' => 'Castelo Branco',
        'country' => 'Portugal',
        'country_code' => 'pt',
        'latitude' => 40.2806,
        'longitude' => -7.5049,
        'nif_nipc' => '316416584',
        'nie' => '2175V64V7',
        'bank_beneficiary' => 'Argenis Jose Carrillo Gonzalez',
        'bank_iban' => 'PT50 0036 0011 9910 0063 053 49',
        'bank_bic' => 'MPIOPTPL',
        'bank_name' => 'Montepio',
        'invoice_notes' => 'Reverse charge.',
        'facebook_link' => 'https://www.facebook.com/argenisdev692/',
        'github_link' => 'https://github.com/argenisdev692',
        'instagram_link' => 'https://www.instagram.com/argenis.dev/',
        'linkedin_link' => 'https://www.linkedin.com/in/argenisdev692/',
        'tiktok_link' => 'https://www.tiktok.com/@argenisdev692',
        'twitter_link' => null,
        ...$overrides,
    ];
}

describe('authorization', function (): void {
    it('turns a guest away from every route', function (string $method, string $route): void {
        company();

        $this->{$method}(route($route))->assertRedirect(route('login'));
    })->with([
        ['get', 'company.show'],
        ['get', 'company.edit'],
        ['put', 'company.update'],
        ['post', 'company.logos.update'],
    ]);

    it('refuses a signed-in user who holds no company permission', function (): void {
        company();

        $this->actingAs(operator([]))->get(route('company.show'))->assertForbidden();
    });

    it('refuses a reader on the write routes', function (): void {
        company();

        $this->actingAs(operator(['VIEW_COMPANY_DATA']))
            ->put(route('company.update'), companyPayload())
            ->assertForbidden();
    });

    it('answers 404 before the installation has been seeded', function (): void {
        $this->actingAs(operator())->get(route('company.show'))->assertNotFound();
    });
});

describe('reading', function (): void {
    it('renders the record for an operator who may view it', function (): void {
        company(['company_name' => 'Argenis Hub', 'email' => 'hello@example.test']);

        $this->actingAs(operator())
            ->get(route('company.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/company/Show')
                ->where('company.company_name', 'Argenis Hub')
                ->where('company.email', 'hello@example.test'));
    });

    it('hands the edit form the browser key for the address autocomplete', function (): void {
        company();
        config()->set('services.google_maps.key', 'browser-key');

        $this->actingAs(operator())
            ->get(route('company.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/company/Edit')
                ->where('google_maps_key', 'browser-key'));
    });

    it('never exposes the auto-increment key', function (): void {
        company();

        $response = $this->actingAs(operator())
            ->getJson(route('company.show'))
            ->assertOk();

        expect($response->json())->not->toHaveKey('id');
    });

    it('falls back to the bundled marks until a logo is uploaded', function (): void {
        company(['logo_path' => null, 'logo_white_path' => null, 'mark_path' => null]);

        $base = rtrim((string) config('app.url'), '/');

        $this->actingAs(operator())
            ->getJson(route('company.show'))
            ->assertOk()
            ->assertJsonPath('logos.logo', $base.'/img/Logo.png')
            ->assertJsonPath('logos.logo_white', $base.'/img/Logo-white.png')
            ->assertJsonPath('logos.mark', $base.'/img/Mark.png');
    });
});

describe('updating', function (): void {
    it('persists the whole editable surface', function (): void {
        $model = company();

        $this->actingAs(operator())
            ->put(route('company.update'), companyPayload())
            ->assertRedirect()
            ->assertSessionHas('status', 'company-updated');

        $model->refresh();

        expect($model->company_name)->toBe('Argenis Hub')
            ->and($model->name)->toBe('Argenis Carrillo Gonzalez')
            ->and($model->address_2)->toBe('R/C Esq.')
            ->and($model->zip_code)->toBe('6200-386')
            ->and($model->latitude)->toBe(40.2806)
            ->and($model->longitude)->toBe(-7.5049)
            ->and($model->linkedin_link)->toBe('https://www.linkedin.com/in/argenisdev692/');
    });

    it('normalizes the values that downstream code compares', function (): void {
        $model = company();

        $this->actingAs(operator())
            ->put(route('company.update'), companyPayload([
                'company_name' => '  Argenis   Hub  ',
                'email' => 'INFO@Argenis.DEV',
                'country_code' => 'pt',
                'zip_code' => ' 6200-386 ',
                'bank_iban' => 'pt50 0036 0011 9910 0063 053 49',
            ]))
            ->assertRedirect();

        $model->refresh();

        expect($model->company_name)->toBe('Argenis Hub')
            ->and($model->email)->toBe('info@argenis.dev')
            ->and($model->country_code)->toBe('PT')
            ->and($model->zip_code)->toBe('6200-386')
            ->and($model->bank_iban)->toBe('PT50003600119910006305349');
    });

    it('keeps the deliberate line breaks in multi-line fields', function (): void {
        $model = company();

        $this->actingAs(operator())
            ->put(route('company.update'), companyPayload([
                'description' => "  First line.\nSecond line.  ",
            ]))
            ->assertRedirect();

        expect($model->refresh()->description)->toBe("First line.\nSecond line.");
    });

    it('stores a cleared field as null rather than an empty string', function (): void {
        $model = company(['state' => 'Castelo Branco']);

        $this->actingAs(operator())
            ->put(route('company.update'), companyPayload(['state' => '']))
            ->assertRedirect();

        expect($model->refresh()->state)->toBeNull();
    });

    it('accepts a postal code the operator overrode by hand', function (): void {
        $model = company(['zip_code' => '6200-386']);

        $this->actingAs(operator())
            ->put(route('company.update'), companyPayload(['zip_code' => '6201-999']))
            ->assertRedirect();

        expect($model->refresh()->zip_code)->toBe('6201-999');
    });

    it('leaves the columns this module does not own alone', function (): void {
        $model = company(['signature_path' => 'company/signature.png']);
        $ownerId = $model->user_id;

        $this->actingAs(operator())->put(route('company.update'), companyPayload())->assertRedirect();

        $model->refresh();

        expect($model->signature_path)->toBe('company/signature.png')
            ->and($model->user_id)->toBe($ownerId);
    });

    it('rejects invalid input', function (array $overrides, string $field): void {
        company();

        $this->actingAs(operator())
            ->putJson(route('company.update'), companyPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'missing trading name' => [['company_name' => ''], 'company_name'],
        'malformed email' => [['email' => 'not-an-email'], 'email'],
        'hostile website scheme' => [['website' => 'javascript:alert(1)'], 'website'],
        'hostile social scheme' => [['linkedin_link' => 'javascript:alert(1)'], 'linkedin_link'],
        'latitude out of range' => [['latitude' => 140.5], 'latitude'],
        'longitude out of range' => [['longitude' => -200.5], 'longitude'],
        'country code too long' => [['country_code' => 'PRT'], 'country_code'],
        'postal code too long' => [['zip_code' => '999999999999999999999'], 'zip_code'],
    ]);

    it('records the change in the audit trail', function (): void {
        $model = company(['company_name' => 'Before']);

        $this->actingAs(operator())
            ->put(route('company.update'), companyPayload(['company_name' => 'After']))
            ->assertRedirect();

        // activitylog v5 keeps the attribute diff in its own `attribute_changes`
        // column; `properties` is reserved for what a caller passes explicitly.
        $activity = $model->activitiesAsSubject()->where('event', 'updated')->latest('id')->first();
        $changes = $activity?->attribute_changes?->toArray() ?? [];

        expect($activity?->log_name)->toBe('company.data')
            ->and($changes['attributes']['company_name'] ?? null)->toBe('After')
            ->and($changes['old']['company_name'] ?? null)->toBe('Before');
    });
});
