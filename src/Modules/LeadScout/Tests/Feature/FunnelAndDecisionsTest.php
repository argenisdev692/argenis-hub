<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutOutreachFactory;
use Database\Factories\ScoutScoreResultFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Services\DecisionRuleEvaluator;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function funnelAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function funnelCompany(User $admin): ScoutCompanyEloquentModel
{
    return ScoutCompanyFactory::new()->spanishAgency()->create();
}

function sentOutreachFor(ScoutCompanyEloquentModel $company, User $admin, array $overrides = []): ScoutOutreachEloquentModel
{
    return ScoutOutreachFactory::new()->sent()->create([
        'company_id' => $company->id,
        'operator_id' => $admin->id,
        ...$overrides,
    ]);
}

it('creates and edits opportunities, several per contact', function (): void {
    $admin = funnelAdmin();
    $company = funnelCompany($admin);
    $outreach = sentOutreachFor($company, $admin);

    $first = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$outreach->uuid}/opportunities", [
            'type' => 'trial', 'hours_per_month' => 40, 'hourly_rate_cents' => 3500, 'status' => 'open',
        ])
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$outreach->uuid}/opportunities", [
            'type' => 'retainer', 'hours_per_month' => 80, 'amount_cents' => 280000, 'status' => 'open',
        ])
        ->assertCreated()
        ->json('data');

    expect($first['uuid'])->not->toBe($second['uuid']);

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/opportunities/{$first['uuid']}", ['status' => 'won'])
        ->assertOk()
        ->assertJsonPath('data.status', 'won');

    $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/outreaches/00000000-0000-7000-8000-000000000000/opportunities', ['type' => 'trial'])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->patchJson('/data/admin/lead-scout/opportunities/00000000-0000-7000-8000-000000000000', ['status' => 'won'])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$outreach->uuid}/opportunities", ['type' => 'bogus'])
        ->assertUnprocessable();
});

it('reports funnel metrics with sample reading', function (): void {
    $admin = funnelAdmin();
    $company = funnelCompany($admin);

    ScoutScoreResultFactory::new()->forCompany($company)->a()->create();

    $positive = sentOutreachFor($company, $admin, ['send_medium' => 'contact_form']);
    $positive->update(['stage' => OutreachStage::Positive->value]);
    sentOutreachFor($company, $admin, ['send_medium' => 'contact_form']);
    sentOutreachFor($company, $admin, ['send_medium' => 'email']);

    ScoutOutreachFactory::new()->create([
        'company_id' => $company->id,
        'operator_id' => $admin->id,
        'stage' => OutreachStage::Draft->value,
    ]);

    ScoutOpportunityEloquentModel::query()->create([
        'outreach_id' => $positive->id,
        'type' => 'retainer',
        'hours_per_month' => 40,
        'amount_cents' => 140000,
        'currency' => 'EUR',
        'status' => 'won',
    ]);

    $metrics = $this->actingAs($admin)->getJson('/data/admin/lead-scout/metrics')->assertOk()->json('data');

    expect($metrics)->toHaveKeys([
        'stages', 'totals', 'by_channel', 'by_variant', 'by_origin', 'by_country',
        'by_wave', 'weekly_volume', 'billing', 'cost_per_qualified_lead',
        'search_effectiveness', 'extraction', 'decisor_coverage', 'sample',
    ])
        ->and($metrics['totals'])->toMatchArray(['sent' => 3, 'responded' => 1, 'positive' => 1])
        ->and($metrics['billing'])->toMatchArray(['hours_billed' => 40, 'revenue_cents' => 140000])
        ->and($metrics['sample']['conclusive'])->toBeFalse()
        ->and($metrics['sample']['message'])->toContain('no concluyente')
        ->and($metrics['decisor_coverage']['ab_leads'])->toBe(1);
});

it('locks decision rules once and branches on the sample', function (): void {
    $admin = funnelAdmin();

    $uuid = $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/decision-rules', [])
        ->assertCreated()
        ->json('data.uuid');

    $locked = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/decision-rules/{$uuid}/lock")
        ->assertOk()
        ->json('data');

    expect($locked['locked_at'])->not->toBeNull()
        ->and($locked['evaluation']['conclusive'])->toBeFalse();

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/decision-rules/{$uuid}/lock")
        ->assertConflict();

    $evaluator = app(DecisionRuleEvaluator::class);
    $rule = ['sample_size' => 150, 'window_days' => 56, 'thresholds' => ['scale_at' => 0.05, 'stop_below' => 0.01]];

    expect($evaluator->evaluate($rule, 30, 0)['outcome']->value)->toBe('inconclusive')
        ->and($evaluator->evaluate($rule, 150, 2)['outcome']->value)->toBe('iterate')
        ->and($evaluator->evaluate($rule, 150, 5)['outcome']->value)->toBe('iterate')
        ->and($evaluator->evaluate($rule, 150, 8)['outcome']->value)->toBe('scale');
});

it('audits opportunity amounts in the activity log', function (): void {
    $admin = funnelAdmin();
    $outreach = sentOutreachFor(funnelCompany($admin), $admin);

    $uuid = $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/outreaches/{$outreach->uuid}/opportunities", [
            'type' => 'retainer', 'hours_per_month' => 80, 'amount_cents' => 280000, 'status' => 'open',
        ])
        ->assertCreated()
        ->json('data.uuid');

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/opportunities/{$uuid}", ['status' => 'won'])
        ->assertOk();

    $logs = Activity::query()->where('log_name', 'lead-scout.opportunity')->oldest('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->last()->attribute_changes['attributes'])->toBe(['status' => 'won']);
});
