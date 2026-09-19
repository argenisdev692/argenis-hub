<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutScoreResultFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
    ScoutCompanyFactory::new()->count(50)->create();
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
    $unscored = ScoutCompanyFactory::new()->create(['name' => 'New Co']);

    ScoutScoreResultFactory::new()->forCompany($low)->create(['tier' => 'C', 'lead_score' => 40, 'confidence' => 60]);
    ScoutScoreResultFactory::new()->forCompany($high)->create(['tier' => 'A', 'lead_score' => 90, 'confidence' => 80]);

    $names = $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/leads?per_page=15')
        ->assertOk()
        ->json('data.*.name');

    expect($names)->toBe(['High Co', 'Low Co', 'New Co']);
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
    ScoutCompanyFactory::new()->create(['created_at' => '2026-03-10 12:00:00']);
    ScoutCompanyFactory::new()->create(['created_at' => '2026-06-10 12:00:00']);

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
