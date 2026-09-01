<?php

declare(strict_types=1);

use App\Models\CompanyData;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Company\Domain\Enums\SocialChannel;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;
use Shared\Infrastructure\Company\CompanyProfile;

/**
 * Reading a row the value objects would refuse to construct.
 *
 * `CompanyMapper` reads forgivingly on purpose: rows written before the value
 * objects existed — or by a seeder, a migration or a DBA — can hold a URL with
 * no scheme, a postal code longer than the VO allows, or a latitude outside
 * -90..90. Those catch blocks are the only thing standing between such a row and
 * a 500 on the very screen the operator would use to correct it, so they need
 * their own coverage: an exception thrown here is invisible to every test that
 * writes through the validated HTTP surface.
 *
 * Writes go through the query builder rather than Eloquent so nothing in the
 * model, the DTO or the FormRequest gets a chance to reject the value first —
 * that is precisely the state being simulated.
 */
beforeEach(function (): void {
    CompanyProfile::forget();
});

/**
 * @param  array<string, mixed>  $columns
 */
function companyWithRawColumns(array $columns, bool $withSocials = false): void
{
    $factory = CompanyData::factory();

    if ($withSocials) {
        $factory = $factory->withSocials();
    }

    $company = $factory->create(['user_id' => User::factory()]);

    DB::table('company_data')->where('id', $company->getKey())->update($columns);
}

it('degrades a hostile stored website to null', function (): void {
    companyWithRawColumns(['website' => 'javascript:alert(1)']);

    expect(app(CompanyRepositoryPort::class)->current()->website)->toBeNull();
});

it('degrades a hostile stored social link to null, leaving the healthy ones alone', function (): void {
    companyWithRawColumns(['linkedin_link' => 'javascript:alert(1)'], withSocials: true);

    $socials = app(CompanyRepositoryPort::class)->current()->socials;

    expect($socials[SocialChannel::Linkedin->value])->toBeNull()
        ->and($socials[SocialChannel::Github->value]?->value)->toStartWith('https://');
});

it('degrades a scheme-less legacy URL rather than throwing', function (): void {
    companyWithRawColumns(['website' => 'www.example.com']);

    expect(app(CompanyRepositoryPort::class)->current()->website)->toBeNull();
});

it('degrades a postal code longer than the value object allows', function (): void {
    companyWithRawColumns(['zip_code' => str_repeat('9', 40)]);

    expect(app(CompanyRepositoryPort::class)->current()->postalCode)->toBeNull();
});

it('degrades a latitude outside -90..90 to no coordinates at all', function (): void {
    companyWithRawColumns(['latitude' => 999.0, 'longitude' => 10.0]);

    expect(app(CompanyRepositoryPort::class)->current()->coordinates)->toBeNull();
});

it('still lets an operator open the settings screen so the bad value can be fixed', function (): void {
    companyWithRawColumns(['website' => 'javascript:alert(1)']);

    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();

    $user = User::factory()->create();
    $user->givePermissionTo(['VIEW_COMPANY_DATA']);

    $this->actingAs($user)
        ->get(route('company.show'))
        ->assertOk();
});

it('never renders a hostile legacy URL on the public endpoint', function (): void {
    companyWithRawColumns(['website' => 'javascript:alert(1)']);

    $response = $this->getJson('/api/public/company');

    $response->assertOk();
    expect($response->json('website'))->toBeNull();
});
