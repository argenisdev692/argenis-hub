<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutContactChannelFactory;
use Database\Factories\ScoutOutreachFactory;
use Database\Factories\ScoutProfileFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Application\Commands\ScoreCompanyHandler;
use Modules\LeadScout\Application\Commands\UpdateBudgetsHandler;
use Modules\LeadScout\Application\DTOs\UpdateBudgetsData;
use Modules\LeadScout\Domain\Enums\ActivityStatus;
use Modules\LeadScout\Infrastructure\Ai\WriteOutreachOpenerAgent;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachStageEventEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;
use Modules\LeadScout\Tests\Support\RecordingAiClient;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function draftsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function tierACompany(): ScoutCompanyEloquentModel
{
    $company = ScoutCompanyFactory::new()->spanishAgency()->create([
        'activity_status' => ActivityStatus::Active,
        'team_size_observed' => 25,
    ]);

    $recent = ['value_text' => 'Content from 2026-06-15'];

    foreach ([
        ['laravel', 'technical'], ['vue_inertia', 'technical'],
        ['stack_db', 'technical'], ['api_ai', 'technical'],
        ['freelance_contract', 'commercial'], ['agency_type', 'commercial'],
        ['multi_vacancies', 'commercial'], ['maintenance_sla', 'recurrent'],
        ['many_cases', 'recurrent'], ['active_vacancy', 'recurrent'],
        ['sitemap_fresh', 'vitality'], ['vacancy_vitality', 'vitality'],
        ['team_5_50', 'vitality'], ['lang_es_pt', 'communication'],
        ['country_pt_es', 'geo_contract'], ['remote', 'remote'],
    ] as [$key, $dimension]) {
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
        ]);
    }

    ScoutSignalEloquentModel::query()->create([
        'company_id' => $company->id,
        'dimension' => 'commercial',
        'signal_key' => 'accepts_external',
        'nature' => 'inference',
        'confidence' => 80,
        'evidence_url' => 'https://agencia.example/partners',
        'evidence_excerpt' => 'Partner program.',
        'captured_at' => CarbonImmutable::parse('2026-09-15'),
        'extraction_method' => 'rule',
    ]);

    ScoutSignalEloquentModel::query()->create([
        'company_id' => $company->id,
        'dimension' => 'vitality',
        'signal_key' => 'recent_content',
        'nature' => 'fact',
        'confidence' => 90,
        'evidence_url' => null,
        ...$recent,
        'captured_at' => CarbonImmutable::parse('2026-09-15'),
        'extraction_method' => 'rule',
    ]);

    return $company;
}

function profileWithProof(User $admin): void
{
    ScoutProfileFactory::new()->create([
        'user_id' => $admin->id,
        'proof_points' => [[
            'title' => 'Vidula', 'summary' => 'Modular SaaS in Laravel and Vue.',
            'technologies' => ['laravel', 'vue'], 'sector' => 'saas',
            'result' => '40% faster onboarding', 'url' => 'https://vidula.example.com', 'order' => 0,
        ]],
    ]);
}

function draftChannel(ScoutCompanyEloquentModel $company): ScoutContactChannelEloquentModel
{
    return ScoutContactChannelFactory::new()->create([
        'company_id' => $company->id,
    ]);
}

it('generates a draft with evidence grounding, template version and legal notice', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    $admin = draftsAdmin();
    profileWithProof($admin);
    $company = tierACompany();
    app(ScoreCompanyHandler::class)->handle($company->uuid, $admin->id);

    $spy = RecordingAiClient::install([
        WriteOutreachOpenerAgent::class => ['opener' => 'Vi vuestra oferta de Laravel y encajo con el perfil que buscáis.'],
    ]);

    $response = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/drafts", [])
        ->assertCreated();

    $data = $response->json('data');
    $outreach = ScoutOutreachEloquentModel::query()->where('uuid', $data['uuid'])->firstOrFail();

    expect($data['stage'])->toBe('draft')
        ->and($data['ai_provider'])->toBe('anthropic')
        ->and($data['ai_model'])->toBe('claude-sonnet-5')
        ->and($data['draft_body'])->toContain('Vi vuestra oferta de Laravel')
        ->and($data['draft_body'])->toContain('Vidula')
        ->and($data['draft_body'])->toContain('Aviso de privacidad')
        ->and($data['draft_body'])->toContain('BAJA')
        ->and($outreach->template_key)->toBe('vacancy')
        ->and($outreach->template_version)->toBe(1)
        ->and($outreach->sender_kind)->toBe('personal_mailbox')
        ->and($response->json('meta.unconfirmed_claims'))->toBeEmpty();

    $prompt = $spy->calls[0]['prompt'];

    expect($prompt)->toContain('Evidencia literal')
        ->and($prompt)->not->toContain('raw_text');
});

it('refuses drafts for tier C, suppressed companies, unknown models and empty budgets', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    RecordingAiClient::install([WriteOutreachOpenerAgent::class => ['opener' => 'Hola.']]);
    $admin = draftsAdmin();
    profileWithProof($admin);

    $plain = ScoutCompanyFactory::new()->spanishAgency()->create();
    app(ScoreCompanyHandler::class)->handle($plain->uuid, $admin->id);

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$plain->uuid}/drafts", [])
        ->assertConflict();

    $company = tierACompany();
    app(ScoreCompanyHandler::class)->handle($company->uuid, $admin->id);

    ScoutSuppressionEloquentModel::query()->create([
        'canonical_domain' => $company->canonical_domain, 'source' => 'manual', 'reason' => 'DNC',
    ]);

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/drafts", [])
        ->assertConflict();

    ScoutSuppressionEloquentModel::query()->delete();

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/drafts", ['provider' => 'nope', 'model' => 'nope-1'])
        ->assertUnprocessable();

    app(UpdateBudgetsHandler::class)->handle(
        UpdateBudgetsData::from(['budgets' => [['category' => 'ai', 'limit_eur' => 0]]]),
    );

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/drafts", [])
        ->assertStatus(402);
});

it('rejects ready with unconfirmed claims and records manual sends with history', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    $admin = draftsAdmin();
    profileWithProof($admin);
    $company = tierACompany();
    app(ScoreCompanyHandler::class)->handle($company->uuid, $admin->id);

    RecordingAiClient::install([WriteOutreachOpenerAgent::class => ['opener' => 'Encajo con vuestro stack.']]);

    $uuid = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/drafts", [])
        ->assertCreated()
        ->json('data.uuid');

    // Unconfirmed AWS claim blocks `ready`.
    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$uuid}", [
            'stage' => 'ready', 'draft_body' => 'Trabajo con AWS cada día.',
        ])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$uuid}", ['stage' => 'ready'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'ready');

    // Invalid jump draft→sent is rejected; sent needs its channel + medium.
    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$uuid}", ['stage' => 'sent'])
        ->assertUnprocessable();

    $channel = draftChannel($company);

    $sent = $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$uuid}", [
            'stage' => 'ready',
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$uuid}", [
            'stage' => 'sent',
            'contact_channel_id' => $channel->uuid,
            'send_medium' => 'contact_form',
            'acknowledge_pending_legal' => true,
            'operator_id' => 999999,
        ])
        ->assertOk()
        ->assertJsonPath('data.stage', 'sent')
        ->assertJsonPath('data.sender_kind', 'personal_mailbox');

    $outreach = ScoutOutreachEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

    expect($outreach->operator_id)->toBe($admin->id)
        ->and($outreach->sent_at)->not->toBeNull()
        ->and($channel->refresh()->status->value)->toBe('used')
        ->and(ScoutOutreachStageEventEloquentModel::query()
            ->where('outreach_id', $outreach->id)->count())->toBeGreaterThanOrEqual(3);
});

it('warns past fifteen manual sends a day', function (): void {
    $admin = draftsAdmin();
    $company = tierACompany();

    for ($i = 0; $i < 16; $i++) {
        ScoutOutreachFactory::new()->sent()->create([
            'company_id' => $company->id,
            'operator_id' => $admin->id,
            'sent_at' => now(),
        ]);
    }

    $draft = ScoutOutreachFactory::new()->create([
        'company_id' => $company->id,
        'operator_id' => $admin->id,
        'stage' => 'ready',
    ]);
    $channel = draftChannel($company);

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$draft->uuid}", [
            'stage' => 'sent',
            'contact_channel_id' => $channel->uuid,
            'send_medium' => 'contact_form',
            'acknowledge_pending_legal' => true,
        ])
        ->assertOk()
        ->assertJsonPath('meta.daily_limit_warning', true)
        ->assertJsonPath('meta.daily_sent', 17);
});

it('leaves no trace when a send is rejected for a missing legal acknowledgement', function (): void {
    $admin = draftsAdmin();
    $company = tierACompany();
    $draft = ScoutOutreachFactory::new()->create([
        'company_id' => $company->id,
        'operator_id' => $admin->id,
        'stage' => 'ready',
    ]);
    $channel = draftChannel($company);

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$draft->uuid}", [
            'stage' => 'sent',
            'contact_channel_id' => $channel->uuid,
            'send_medium' => 'contact_form',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('acknowledge_pending_legal');

    $draft->refresh();

    expect($draft->stage->value)->toBe('ready')
        ->and($draft->sent_at)->toBeNull()
        ->and($draft->contact_channel_id)->toBeNull()
        ->and($channel->refresh()->status->value)->toBe('active');
});
