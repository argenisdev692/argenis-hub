<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutProfileFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Application\Commands\ScoreCompanyHandler;
use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutScoreResultEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function scoringAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function storedSignal(ScoutCompanyEloquentModel $company, string $key, string $dimension, array $overrides = []): void
{
    ScoutSignalEloquentModel::query()->create([
        'company_id' => $company->id,
        'dimension' => $dimension,
        'signal_key' => $key,
        'nature' => 'fact',
        'confidence' => 85,
        'evidence_url' => 'https://agencia.example/pagina',
        'evidence_excerpt' => 'Evidencia literal.',
        'captured_at' => CarbonImmutable::parse('2026-09-15'),
        'extraction_method' => 'rule',
        ...$overrides,
    ]);
}

function sevillaCompany(): ScoutCompanyEloquentModel
{
    $company = ScoutCompanyFactory::new()->spanishAgency()->create([
        'activity_status' => ActivityStatus::Active,
        'team_size_observed' => 25,
    ]);

    $recent = ['value_text' => 'Content from 2026-06-15'];

    foreach ([
        ['laravel', 'technical'], ['vue_inertia', 'technical'],
        ['stack_db', 'technical'], ['stack_db', 'technical'], ['stack_db', 'technical'],
        ['api_ai', 'technical'], ['freelance_contract', 'commercial'], ['agency_type', 'commercial'],
        ['multi_vacancies', 'commercial'], ['maintenance_sla', 'recurrent'], ['many_cases', 'recurrent'],
        ['active_vacancy', 'recurrent'], ['sitemap_fresh', 'vitality'], ['vacancy_vitality', 'vitality'],
        ['team_5_50', 'vitality'], ['lang_es_pt', 'communication'], ['country_pt_es', 'geo_contract'],
        ['remote', 'remote'],
    ] as [$key, $dimension]) {
        storedSignal($company, $key, $dimension);
    }

    storedSignal($company, 'accepts_external', 'commercial', ['nature' => 'inference']);
    storedSignal($company, 'long_term', 'recurrent', ['nature' => 'inference']);
    storedSignal($company, 'recent_content', 'vitality', $recent);

    return $company;
}

it('scores deterministically with a single current result and linked reasons', function (): void {
    $admin = scoringAdmin();
    ScoutProfileFactory::new()->create(['user_id' => $admin->id]);
    $company = sevillaCompany();

    $first = app(ScoreCompanyHandler::class)->handle($company->uuid, $admin->id);
    $second = app(ScoreCompanyHandler::class)->handle($company->uuid, $admin->id);

    expect($first->lead_score)->toBe(87)
        ->and($second->lead_score)->toBe($first->lead_score)
        ->and($first->tier)->toBe(Tier::A)
        ->and($company->refresh()->needs_research)->toBeFalse()
        ->and(ScoutScoreResultEloquentModel::query()->where('company_id', $company->id)->where('is_current', true)->count())->toBe(1)
        ->and($first->reasons()->count())->toBe(20)
        ->and($first->reasons()->whereNotNull('signal_id')->count())->toBeGreaterThanOrEqual(19);
});

it('rescores over http with reasons and answers 404 unknown', function (): void {
    $admin = scoringAdmin();
    ScoutProfileFactory::new()->create(['user_id' => $admin->id]);
    $company = sevillaCompany();

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/rescore")
        ->assertOk()
        ->assertJsonPath('data.lead_score', 87)
        ->assertJsonPath('data.tier', 'A')
        ->assertJsonCount(20, 'data.reasons');

    $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/leads/00000000-0000-7000-8000-000000000000/rescore')
        ->assertNotFound();
});

it('exports leads and funnel as xlsx and pdf with filters applied', function (): void {
    $admin = scoringAdmin();
    ScoutProfileFactory::new()->create(['user_id' => $admin->id]);
    $company = sevillaCompany();
    app(ScoreCompanyHandler::class)->handle($company->uuid, $admin->id);

    $disposition = $this->actingAs($admin)
        ->get('/data/admin/lead-scout/leads/export?dataset=leads&format=xlsx')
        ->assertOk()
        ->headers->get('content-disposition');

    expect($disposition)->toStartWith('attachment;')->toEndWith('lead-scout-leads.xlsx');

    $this->actingAs($admin)
        ->get('/data/admin/lead-scout/leads/export?dataset=leads&format=pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Tier filter respected: only Tier A rows.
    $csv = $this->actingAs($admin)
        ->get('/data/admin/lead-scout/leads/export?dataset=leads&format=csv&tier[]=A')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain($company->canonical_domain);

    $empty = $this->actingAs($admin)
        ->get('/data/admin/lead-scout/leads/export?dataset=leads&format=csv&tier[]=C')
        ->assertOk()
        ->streamedContent();

    expect($empty)->not->toContain($company->canonical_domain);

    $this->actingAs($admin)
        ->get('/data/admin/lead-scout/leads/export?dataset=funnel&format=xlsx')
        ->assertOk();
});

it('denies export without permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('GUEST');

    $this->actingAs($user)
        ->get('/data/admin/lead-scout/leads/export?dataset=leads&format=csv')
        ->assertForbidden();
});
