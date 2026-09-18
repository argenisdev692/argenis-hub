<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutPrivacyRequestEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function contactsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function contactCompany(): ScoutCompanyEloquentModel
{
    return ScoutCompanyFactory::new()->spanishAgency()->create();
}

function contactPayload(array $overrides = []): array
{
    return [
        'full_name' => 'Ana Ruiz',
        'role_title' => 'CEO',
        'role_category' => 'executive',
        ...$overrides,
    ];
}

it('creates and updates contacts with primary switching', function (): void {
    $admin = contactsAdmin();
    $company = contactCompany();

    $uuid = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/contacts", contactPayload())
        ->assertCreated()
        ->json('data.uuid');

    $second = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/contacts", contactPayload([
            'full_name' => 'João Silva', 'role_title' => 'CTO', 'role_category' => 'technical_lead', 'is_primary' => true,
        ]))
        ->assertCreated()
        ->json('data.uuid');

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/contacts/{$uuid}", contactPayload(['is_primary' => true]))
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    expect(ScoutContactEloquentModel::query()->where('company_id', $company->id)->where('is_primary', true)->count())->toBe(1)
        ->and(ScoutContactEloquentModel::query()->where('uuid', $second)->value('is_primary'))->toBeFalse();
});

it('rejects excluded titles and foreign emails with 422', function (): void {
    $admin = contactsAdmin();
    $company = contactCompany();

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/contacts", contactPayload(['role_title' => 'Talent Acquisition']))
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/contacts", contactPayload([
            'role_title' => 'CEO', 'published_email' => 'ana@gmail.com',
        ]))
        ->assertUnprocessable();

    expect(ScoutContactEloquentModel::query()->where('company_id', $company->id)->count())->toBe(0);
});

it('anonymizes on objection, registers it, and refuses re-adding with 409', function (): void {
    $admin = contactsAdmin();
    $company = contactCompany();

    $uuid = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/contacts", contactPayload([
            'published_email' => 'ana@'.$company->canonical_domain,
        ]))
        ->assertCreated()
        ->json('data.uuid');

    $this->actingAs($admin)->postJson("/data/admin/lead-scout/contacts/{$uuid}/objection")->assertNoContent();

    $contact = ScoutContactEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

    expect($contact->full_name)->toBeNull()
        ->and($contact->published_email)->toBeNull()
        ->and($contact->anonymized_at)->not->toBeNull()
        ->and(ScoutContactObjectionEloquentModel::query()
            ->where('person_hash', DecisionMakerExtractor::personHash('Ana Ruiz', $company->canonical_domain))->exists())->toBeTrue();

    $ledger = ScoutPrivacyRequestEloquentModel::query()->latest('id')->firstOrFail();

    expect($ledger->request_type->value)->toBe('objection')
        ->and(json_encode($ledger->toArray()))->not->toContain('Ana Ruiz');

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/contacts", contactPayload())
        ->assertConflict();
});

it('marks broken channels so they stop being recommended', function (): void {
    $admin = contactsAdmin();
    $company = contactCompany();
    $channel = ScoutContactChannelEloquentModel::query()->create([
        'company_id' => $company->id,
        'channel_type' => 'contact_form',
        'url' => 'https://'.$company->canonical_domain.'/contacto',
        'audience' => 'leadership_sales',
    ]);

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/channels/{$channel->uuid}", ['status' => 'broken'])
        ->assertOk()
        ->assertJsonPath('data.status', 'broken');

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/channels/{$channel->uuid}", ['status' => 'bogus'])
        ->assertUnprocessable();
});
