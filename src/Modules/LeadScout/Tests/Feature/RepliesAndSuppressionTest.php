<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutContactFactory;
use Database\Factories\ScoutOutreachFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Application\Commands\EnrichCompanyHandler;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactObjectionEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachStageEventEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function replyAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function sentOutreach(ScoutCompanyEloquentModel $company, User $operator): ScoutOutreachEloquentModel
{
    return ScoutOutreachFactory::new()->sent()->create([
        'company_id' => $company->id,
        'operator_id' => $operator->id,
    ]);
}

it('moves interested to positive and not_interested to lost with history', function (): void {
    $admin = replyAdmin();
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();

    $positive = sentOutreach($company, $admin);

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$positive->uuid}/reply", ['outcome' => 'interested'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'positive');

    $lost = sentOutreach($company, $admin);

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$lost->uuid}/reply", ['outcome' => 'not_interested'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'lost');

    expect(ScoutOutreachStageEventEloquentModel::query()
        ->where('outreach_id', $positive->id)->count())->toBe(1)
        ->and(ScoutOutreachStageEventEloquentModel::query()
            ->where('outreach_id', $positive->id)->value('reply_outcome')->value)->toBe('interested');
});

it('rejects replies on unsent outreaches', function (): void {
    $admin = replyAdmin();
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();
    $draft = ScoutOutreachFactory::new()->create(['company_id' => $company->id, 'operator_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$draft->uuid}/reply", ['outcome' => 'interested'])
        ->assertUnprocessable();
});

it('suppresses absolutely on unsubscribe, including the nominative person', function (): void {
    $admin = replyAdmin();
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();

    $contact = ScoutContactFactory::new()->create([
        'company_id' => $company->id,
        'full_name' => 'Ana Ruiz',
        'published_email' => 'ana@'.$company->canonical_domain,
        'email_kind' => 'nominative',
    ]);

    $outreach = sentOutreach($company, $admin);
    $outreach->update(['contact_id' => $contact->id]);

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$outreach->uuid}/reply", ['outcome' => 'unsubscribe'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'do_not_contact');

    expect(ScoutSuppressionEloquentModel::query()->where('canonical_domain', $company->canonical_domain)->exists())->toBeTrue()
        ->and($contact->refresh()->anonymized_at)->not->toBeNull()
        ->and(ScoutContactObjectionEloquentModel::query()->count())->toBe(1);
});

it('lets no entry propose a suppressed company again', function (): void {
    $admin = replyAdmin();
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();
    $outreach = sentOutreach($company, $admin);

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$outreach->uuid}/reply", ['outcome' => 'unsubscribe'])
        ->assertOk();

    // Manual intake, scoring, enrichment and drafts all bounce with 409.
    $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/leads', ['name' => $company->name, 'url' => 'https://'.$company->canonical_domain])
        ->assertConflict();

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/rescore")
        ->assertConflict();

    expect(app(EnrichCompanyHandler::class)->handle($company->uuid)['status'])
        ->toBe('suppressed');

    expect(ScoutOutreachEloquentModel::query()->count())->toBe(1);
});

it('lifts suppressions only from console with evidence', function (): void {
    ScoutSuppressionEloquentModel::query()->create([
        'canonical_domain' => 'vuelta.example', 'source' => 'manual', 'reason' => 'DNC',
    ]);

    $this->artisan('lead-scout:privacy', ['action' => 'lift-suppression', '--domain' => 'vuelta.example'])
        ->assertFailed();

    $this->artisan('lead-scout:privacy', [
        'action' => 'lift-suppression', '--domain' => 'vuelta.example', '--evidence' => 'The company wrote back asking to talk.',
    ])->assertSuccessful();

    expect(ScoutSuppressionEloquentModel::query()->where('canonical_domain', 'vuelta.example')->exists())->toBeFalse();
});
