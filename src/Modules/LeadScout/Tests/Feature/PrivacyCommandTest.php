<?php

declare(strict_types=1);

use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutContactFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutPrivacyRequestEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('serves search, export, erase and object without storing PII in the ledger', function (): void {
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();
    $contact = ScoutContactFactory::new()->create([
        'company_id' => $company->id,
        'full_name' => 'Ana Ruiz Herrera',
        'published_email' => 'ana.ruiz@example-agency.es',
    ]);

    $this->artisan('lead-scout:privacy', ['action' => 'search', '--name' => 'Ana'])
        ->assertSuccessful();

    $this->artisan('lead-scout:privacy', ['action' => 'erase', '--email' => 'ana.ruiz@example-agency.es'])
        ->assertSuccessful();

    expect($contact->refresh()->anonymized_at)->not->toBeNull()
        ->and($contact->refresh()->full_name)->toBeNull();

    $output = sys_get_temp_dir().'/lead-scout-privacy-test-'.uniqid().'.json';

    try {
        $this->artisan('lead-scout:privacy', ['action' => 'export', '--name' => 'Ana', '--output' => $output])
            ->assertSuccessful();

        expect(is_file($output))->toBeTrue();
    } finally {
        if (is_file($output)) {
            unlink($output);
        }
    }

    $ledgers = ScoutPrivacyRequestEloquentModel::query()->orderBy('id')->get();
    $dump = $ledgers->toJson();

    expect($ledgers)->toHaveCount(3)
        ->and($dump)->not->toContain('Ana')
        ->and($dump)->not->toContain('ana.ruiz')
        ->and($ledgers->pluck('subject_ref')->all())->each
        ->toMatch('/^[0-9a-f]{64}$/');

    $this->artisan('lead-scout:privacy', ['action' => 'object', '--name' => 'An'])
        ->assertFailed();

    $this->artisan('lead-scout:privacy', ['action' => 'search', '--name' => 'An'])
        ->assertFailed();
});

it('registers an objection hash that blocks re-extraction', function (): void {
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();
    ScoutContactFactory::new()->create([
        'company_id' => $company->id,
        'full_name' => 'Pedro Gil Sanz',
    ]);

    $this->artisan('lead-scout:privacy', ['action' => 'object', '--name' => 'Pedro Gil'])
        ->assertSuccessful();

    $hash = DecisionMakerExtractor::personHash('Pedro Gil Sanz', (string) $company->canonical_domain);

    expect(ScoutContactObjectionEloquentModel::query()->where('person_hash', $hash)->exists())->toBeTrue()
        ->and(ScoutPrivacyRequestEloquentModel::query()->where('request_type', 'objection')->exists())->toBeTrue();
});

it('keeps lift-suppression behind evidence', function (): void {
    $this->artisan('lead-scout:privacy', ['action' => 'lift-suppression', '--domain' => 'missing.example'])
        ->assertFailed();
});
