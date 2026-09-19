<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\CvFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;
use Modules\LeadScout\Domain\Exceptions\CvNotImportableException;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function profileAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function cvFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/cv-anonymized.md');
}

function operatorCv(User $owner, array $overrides = []): CvEloquentModel
{
    return CvFactory::new()->markdown()->create([
        'user_id' => $owner->id,
        'is_primary' => true,
        'raw_text' => cvFixture(),
        ...$overrides,
    ]);
}

it('imports confirmed and potential skills plus citable proof points', function (): void {
    $admin = profileAdmin();
    $cv = operatorCv($admin);

    $response = $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $cv->uuid])
        ->assertCreated();

    $data = $response->json('data');

    expect($data['confirmed_skills'])->toContain('laravel', 'vue', 'inertia', 'livewire', 'postgresql', 'supabase', 'phpunit')
        ->and($data['confirmed_skills'])->not->toContain('aws', 'pest', 'forge')
        ->and($data['potential_skills'])->toContain('aws', 'pest', 'forge')
        ->and(array_column($data['proof_points'], 'title'))
        ->toContain('Vidula', 'AquaShield', 'Servispin', 'Tutorial Cleanup API', 'Restoration Control')
        ->and($data['languages'])->toMatchArray(['es' => 'native', 'pt' => 'resident', 'en' => 'B1'])
        ->and($data['stale'])->toBeFalse();

    // The CV text never leaks into responses (spec FR-35).
    expect($response->getContent())
        ->not->toContain('+34 600 123 456')
        ->and($response->getContent())->not->toContain('operator@example.com')
        ->and($response->getContent())->not->toContain('raw_text');
});

it('mints a new version on every import and keeps a single current row', function (): void {
    $admin = profileAdmin();
    $cv = operatorCv($admin);

    $this->actingAs($admin)->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $cv->uuid])->assertCreated();
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $cv->uuid])->assertCreated();

    expect(ScoutProfileEloquentModel::query()->where('user_id', $admin->id)->max('version'))->toBe(2)
        ->and(ScoutProfileEloquentModel::query()->where('user_id', $admin->id)->where('is_current', true)->count())->toBe(1);
});

it('returns 404 for a foreign or deleted cv', function (): void {
    $admin = profileAdmin();
    $other = User::factory()->create();

    $foreign = operatorCv($other);
    $deleted = operatorCv($admin);
    $deleted->delete();

    $this->actingAs($admin)->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $foreign->uuid])->assertNotFound();
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $deleted->uuid])->assertNotFound();
});

it('returns 422 for a cv without extracted text', function (): void {
    $admin = profileAdmin();
    $pdf = CvFactory::new()->pdf()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $pdf->uuid])
        ->assertUnprocessable()
        ->assertJson(['code' => CvNotImportableException::CODE]);
});

it('flags the profile stale after the cv changes', function (): void {
    $admin = profileAdmin();
    $cv = operatorCv($admin);

    $this->actingAs($admin)->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $cv->uuid])->assertCreated();

    $cv->update(['raw_text' => cvFixture()."\n\n## **CERTIFICATIONS**\n\n- AWS Certified"]);

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/profile')
        ->assertOk()
        ->assertJsonPath('data.stale', true);
});

it('lists the operator cvs without raw text and edits the profile by version', function (): void {
    $admin = profileAdmin();
    operatorCv($admin);

    $cvs = $this->actingAs($admin)->getJson('/data/admin/lead-scout/profile/cvs')->assertOk()->json('data');

    expect($cvs)->toHaveCount(1)
        ->and($cvs[0])->not->toHaveKey('raw_text')
        ->and($cvs[0]['importable'])->toBeTrue();

    $cv = CvEloquentModel::query()->firstOrFail();
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $cv->uuid])->assertCreated();

    $updated = $this->actingAs($admin)
        ->putJson('/data/admin/lead-scout/profile', ['weights' => ['technical' => 25], 'min_rate_cents' => 3500])
        ->assertOk()
        ->json('data');

    expect($updated['version'])->toBe(2)
        ->and($updated['weights'])->toMatchArray(['technical' => 25])
        ->and($updated['min_rate_cents'])->toBe(3500);
});

it('returns 404 when no profile exists yet', function (): void {
    $this->actingAs(profileAdmin())->getJson('/data/admin/lead-scout/profile')->assertNotFound();
});

it('imports a pdf cv once it carries extracted text', function (): void {
    $admin = profileAdmin();
    $pdf = CvFactory::new()->pdf()->create([
        'user_id' => $admin->id,
        'is_primary' => true,
        'raw_text' => cvFixture(),
    ]);

    $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/profile/import-cv', ['cv_uuid' => $pdf->uuid])
        ->assertCreated();

    $cvs = $this->actingAs($admin)->getJson('/data/admin/lead-scout/profile/cvs')->assertOk()->json('data');

    expect($cvs[0]['importable'])->toBeTrue();
});
