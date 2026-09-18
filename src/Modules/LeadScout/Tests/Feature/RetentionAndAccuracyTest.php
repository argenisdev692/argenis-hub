<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutContactFactory;
use Database\Factories\ScoutOutreachFactory;
use Database\Factories\ScoutProfileFactory;
use Database\Factories\ScoutScoreResultFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Application\Commands\PruneLeadContactsHandler;
use Modules\LeadScout\Infrastructure\Ai\WriteOutreachOpenerAgent;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchedPageEloquentModel;
use Modules\LeadScout\Tests\Support\RecordingAiClient;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function retentionAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('anonymizes expired decisors and prunes old markdown', function (): void {
    $now = CarbonImmutable::parse('2026-09-17');
    $company = ScoutCompanyFactory::new()->create();

    $stale = ScoutContactFactory::new()->create([
        'company_id' => $company->id,
        'contact_deadline_at' => $now->subDays(40)->toDateTimeString(),
    ]);
    $fresh = ScoutContactFactory::new()->create([
        'company_id' => $company->id,
        'contact_deadline_at' => $now->addDays(10)->toDateTimeString(),
    ]);

    $oldDeal = ScoutOutreachFactory::new()->sent()->create([
        'company_id' => $company->id,
        'operator_id' => retentionAdmin()->id,
        'sent_at' => $now->subMonths(13)->toDateTimeString(),
    ]);
    $oldContact = ScoutContactFactory::new()->create(['company_id' => $company->id]);
    $oldDeal->update(['contact_id' => $oldContact->id]);

    $recentDeal = ScoutOutreachFactory::new()->sent()->create([
        'company_id' => $company->id,
        'operator_id' => retentionAdmin()->id,
        'sent_at' => $now->subMonths(2)->toDateTimeString(),
    ]);
    $recentContact = ScoutContactFactory::new()->create(['company_id' => $company->id]);
    $recentDeal->update(['contact_id' => $recentContact->id]);

    $oldPage = ScoutFetchedPageEloquentModel::query()->create([
        'company_id' => $company->id,
        'url' => 'https://example.com/vieja',
        'content_markdown' => str_repeat('x', 100),
        'content_hash' => hash('sha256', 'old'),
        'forms_summary' => ['forms' => []],
        'fetched_at' => $now->subDays(40)->toDateTimeString(),
    ]);

    $report = app(PruneLeadContactsHandler::class)->handle($now);

    expect($report)->toMatchArray(['contacts' => 2, 'pages' => 1])
        ->and($stale->refresh()->anonymized_at)->not->toBeNull()
        ->and($stale->refresh()->role_title)->not->toBeNull()
        ->and($fresh->refresh()->anonymized_at)->toBeNull()
        ->and($oldContact->refresh()->anonymized_at)->not->toBeNull()
        ->and($recentContact->refresh()->anonymized_at)->toBeNull()
        ->and($oldPage->refresh()->content_markdown)->toBeNull()
        ->and($oldPage->refresh()->content_hash)->toBe(hash('sha256', 'old'));

    $this->artisan('lead-scout:prune')->assertSuccessful();
});

it('refuses ready without the privacy notice', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    $admin = retentionAdmin();
    ScoutProfileFactory::new()->create(['user_id' => $admin->id]);
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();
    ScoutScoreResultFactory::new()->forCompany($company)->a()->create();

    RecordingAiClient::install([WriteOutreachOpenerAgent::class => ['opener' => 'Encajo con vuestro stack.']]);

    $uuid = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/drafts", [])
        ->assertCreated()
        ->json('data.uuid');

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$uuid}", [
            'stage' => 'ready',
            'draft_body' => 'Trabajo con Laravel cada día.',
        ])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/outreaches/{$uuid}", [
            'stage' => 'ready',
            'draft_body' => 'Trabajo con Laravel cada día. Aviso de privacidad: X. Baja: responda BAJA.',
        ])
        ->assertOk();
});

function reverifyCompany(User $admin, string $evidenceBody): array
{
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();
    ScoutScoreResultFactory::new()->forCompany($company)->a()->create();

    $contact = ScoutContactFactory::new()->primary()->create([
        'company_id' => $company->id,
        'full_name' => 'Ana Ruiz',
        'last_verified_at' => CarbonImmutable::parse('2026-05-01')->toDateTimeString(),
    ]);
    $contact->update(['evidence_url' => 'https://'.$company->canonical_domain.'/equipo']);

    Http::fake([
        'https://'.$company->canonical_domain.'/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://'.$company->canonical_domain.'/equipo' => Http::response(
            "<html><body>{$evidenceBody}</body></html>", 200, ['Content-Type' => 'text/html'],
        ),
    ]);

    return [$company, $contact];
}

it('anonymizes decisors gone from the source and warns on unreachable pages', function (): void {
    config()->set('ai.providers.anthropic.key', 'test-key');
    $admin = retentionAdmin();
    ScoutProfileFactory::new()->create(['user_id' => $admin->id]);

    RecordingAiClient::install([WriteOutreachOpenerAgent::class => ['opener' => 'Encajo con vuestro stack.']]);

    [$company, $contact] = reverifyCompany($admin, '<p>Nuestro equipo: Pedro Gil, CTO.</p>');

    $response = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company->uuid}/drafts", [])
        ->assertCreated();

    expect($response->json('meta.warnings'))->not->toBeEmpty()
        ->and($contact->refresh()->anonymized_at)->not->toBeNull();

    [$company2] = reverifyCompany($admin, 'Forbidden');

    Http::fake([
        'https://'.$company2->canonical_domain.'/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://'.$company2->canonical_domain.'/equipo' => Http::response('Denied', 403),
    ]);

    $blocked = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/leads/{$company2->uuid}/drafts", [])
        ->assertCreated();

    expect($blocked->json('meta.warnings'))->not->toBeEmpty();
});
