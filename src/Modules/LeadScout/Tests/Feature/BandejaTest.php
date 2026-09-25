<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutScoreResultFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function bandejaAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('lists filtered leads in a bounded query count', function (): void {
    $admin = bandejaAdmin();
    // Scored companies: an unscored row is not a lead (see the next test).
    ScoutCompanyFactory::new()->count(50)->tierA()->create();
    DB::enableQueryLog();

    // N+1 proof: tripling the rows must not add a single query. The absolute
    // total includes Auth/permission/session bootstrap (~15); the module's
    // own reads stay at 3 (count + page + eager scores) on a cloud database.
    // Warm up: the first request creates the auth session + login audit.
    // What follows measures steady-state reads only.
    $this->actingAs($admin)->getJson('/data/admin/lead-scout/status')->assertOk();

    DB::flushQueryLog();
    $response = $this->actingAs($admin)->getJson('/data/admin/lead-scout/leads?per_page=15')->assertOk();
    $small = count(DB::getQueryLog());

    DB::flushQueryLog();
    $this->actingAs($admin)->getJson('/data/admin/lead-scout/leads?per_page=50')->assertOk();
    $large = count(DB::getQueryLog());

    expect($large)->toBe($small)
        ->and($small)->toBeLessThanOrEqual(30)
        ->and($response->json('meta.total'))->toBe(50)
        ->and($response->json('data'))->toHaveCount(15);

    $filtered = $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?country[]=PT')
        ->assertOk()
        ->json('data');

    expect($filtered)->each(fn ($row) => expect($row['country'])->toBe('PT'));
});

it('orders the bandeja by tier, score and confidence', function (): void {
    $admin = bandejaAdmin();

    $low = ScoutCompanyFactory::new()->create(['name' => 'Low Co']);
    $high = ScoutCompanyFactory::new()->create(['name' => 'High Co']);
    $scored = ScoutCompanyFactory::new()->create(['name' => 'New Co']);

    ScoutScoreResultFactory::new()->forCompany($low)->create(['tier' => 'C', 'lead_score' => 40, 'confidence' => 60]);
    ScoutScoreResultFactory::new()->forCompany($high)->create(['tier' => 'A', 'lead_score' => 90, 'confidence' => 80]);
    ScoutScoreResultFactory::new()->forCompany($scored)->create(['tier' => 'C', 'lead_score' => 10, 'confidence' => 20]);

    $names = $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?per_page=15')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['High Co', 'Low Co', 'New Co']);
});

it('hides unscored companies and shows them only on demand', function (): void {
    $admin = bandejaAdmin();
    $scored = ScoutCompanyFactory::new()->create(['name' => 'Scored Co']);
    ScoutCompanyFactory::new()->create(['name' => 'Still Enriching']);

    ScoutScoreResultFactory::new()->forCompany($scored)->create(['tier' => 'B', 'lead_score' => 70, 'confidence' => 60]);

    // Default: only the scored lead. A company without a current score cannot
    // be contacted, explained or exported — it is pipeline work, not a lead.
    $default = $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads')
        ->assertOk();

    expect($default->json('data.*.name'))->toBe(['Scored Co'])
        ->and($default->json('meta.total'))->toBe(1);

    // Opt-in for debugging an incomplete pipeline.
    $all = $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?scored_only=false')
        ->assertOk();

    expect($all->json('meta.total'))->toBe(2);

    // A discarded lead is still a scored lead: the reason must stay visible.
    $discarded = ScoutCompanyFactory::new()->create(['name' => 'Solo Freelancer']);
    ScoutScoreResultFactory::new()->forCompany($discarded)->create(['tier' => 'discarded', 'lead_score' => 5, 'confidence' => 50, 'discard_reason' => 'solo_freelancer']);

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?tier[]=discarded')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Solo Freelancer');
});

it('never offers a suppressed company to the bandeja', function (): void {
    $admin = bandejaAdmin();
    $company = ScoutCompanyFactory::new()->create(['name' => 'Retired Co']);
    ScoutScoreResultFactory::new()->forCompany($company)->create(['tier' => 'B', 'lead_score' => 70, 'confidence' => 60]);
    ScoutSuppressionEloquentModel::query()->create([
        'canonical_domain' => $company->canonical_domain,
        'source' => 'manual',
        'reason' => 'DNC',
    ]);

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    // Even with the unscored opt-in, suppression prevails (FR-43).
    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?scored_only=false')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('shows the full detail graph in a bounded query count', function (): void {
    $admin = bandejaAdmin();
    $company = ScoutCompanyFactory::new()->spanishAgency()->create();
    DB::enableQueryLog();

    DB::flushQueryLog();

    $response = $this->actingAs($admin)
        ->getJson("/data/admin/lead-scout/leads/{$company->uuid}")
        ->assertOk();

    // One query per relation, never per row.
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(35);

    $data = $response->json();

    expect($data)->toHaveKeys(['company', 'postings', 'score', 'reasons', 'decisors', 'channels', 'outreaches', 'cold_email_allowed', 'employment_application'])
        ->and($data['company']['domain'])->toBe($company->canonical_domain);

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads/00000000-0000-7000-8000-000000000000')
        ->assertNotFound();
});

it('denies bandeja routes without permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('GUEST');

    $this->actingAs($user)->getJson('/data/admin/lead-scout/leads')->assertForbidden();
    $this->actingAs($user)->get('/lead-scout')->assertForbidden();
});

it('accepts single-bound date filters and rejects an inverted range', function (): void {
    $admin = bandejaAdmin();
    $march = ScoutCompanyFactory::new()->create(['created_at' => '2026-03-10 12:00:00']);
    $june = ScoutCompanyFactory::new()->create(['created_at' => '2026-06-10 12:00:00']);
    ScoutScoreResultFactory::new()->forCompany($march)->create(['tier' => 'B', 'lead_score' => 70, 'confidence' => 60]);
    ScoutScoreResultFactory::new()->forCompany($june)->create(['tier' => 'B', 'lead_score' => 70, 'confidence' => 60]);

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?date_from=2026-05-01')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?date_to=2026-03-10')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?date_from=2026-06-01&date_to=2026-03-01')
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?signal_type[]=bogus')
        ->assertUnprocessable();
});
