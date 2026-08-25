<?php

declare(strict_types=1);

use App\Models\CompanyData;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Shared\Infrastructure\Company\CompanyProfile;

beforeEach(function (): void {
    CompanyProfile::forget();
});

test('the landing page renders for guests', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('the landing page carries the company contact block', function () {
    $user = User::factory()->create();

    CompanyData::factory()->create([
        'user_id' => $user->id,
        'email' => 'hello@example.test',
        'linkedin_link' => 'https://www.linkedin.com/in/example/',
        'github_link' => 'https://github.com/example',
        'instagram_link' => 'https://www.instagram.com/example/',
        'facebook_link' => 'https://www.facebook.com/example/',
        'tiktok_link' => 'https://www.tiktok.com/@example',
        'twitter_link' => null,
    ]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('company.support_email', 'hello@example.test')
            ->where('company.socials.linkedin', 'https://www.linkedin.com/in/example/')
            ->where('company.socials.github', 'https://github.com/example')
            ->where('company.socials.instagram', 'https://www.instagram.com/example/')
            ->where('company.socials.facebook', 'https://www.facebook.com/example/')
            ->where('company.socials.tiktok', 'https://www.tiktok.com/@example')
            ->missing('company.socials.twitter')
        );
});

test('the company seeder stores every social channel including github', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(CompanySeeder::class);

    $company = CompanyData::query()->firstOrFail();

    // The description is AI context for the Post module's topic ideation, so a
    // stale one silently steers every generated draft at the wrong product.
    expect($company->description)
        ->toContain('content generation')
        ->toContain('lead campaign')
        ->toContain('appointment')
        ->toContain('invoicing')
        ->toContain('ATS')
        ->not->toContain('classroom');

    expect($company->email)->toBe('info@argenis.dev')
        ->and($company->github_link)->toBe('https://github.com/argenisdev692')
        ->and($company->linkedin_link)->toBe('https://www.linkedin.com/in/argenisdev692/')
        ->and($company->tiktok_link)->toBe('https://www.tiktok.com/@argenisdev692?lang=es-419')
        ->and($company->facebook_link)->toBe('https://www.facebook.com/argenisdev692/')
        ->and($company->instagram_link)->toBe('https://www.instagram.com/argenis.dev/');
});

test('the company seeder is idempotent', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(CompanySeeder::class);
    $this->seed(CompanySeeder::class);

    expect(CompanyData::query()->count())->toBe(1);
});
