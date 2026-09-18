<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function studioAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function studioProfilePayload(): array
{
    return [
        'name' => 'Fullstack Remote',
        'slug' => 'fullstack',
        'base_city' => 'Lisbon',
        'base_country' => 'PT',
        'accepted_remote_scopes' => ['remote_global', 'remote_eu', 'remote_pt_es'],
        'stack_must' => ['Laravel'],
        'stack_reject' => ['WordPress'],
        'years_baseline' => 4,
    ];
}

function studioPostingPayload(string $profileUuid): array
{
    return [
        'profile_uuid' => $profileUuid,
        'title' => 'Senior Fullstack Developer (Laravel + Vue)',
        'canonical_url' => 'https://boards.greenhouse.io/acme/jobs/4242',
        'employer_name' => 'Acme',
        'location_text' => 'Remote, EU',
        'source' => 'greenhouse',
        'discovery_channel' => 'employer_ats',
        'text' => 'We need a Senior Fullstack Developer with Laravel and Vue.js experience. Remote within the EU.',
        'requirements' => [
            ['canonical_name' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
            ['canonical_name' => 'Vue.js', 'tag' => 'required', 'nature' => 'hard'],
            ['canonical_name' => 'Docker', 'tag' => 'preferred', 'nature' => 'hard'],
        ],
    ];
}

function scoreInput(): array
{
    return [
        'cv_skills' => [
            ['name' => 'Laravel', 'evidence' => 'in_bullet', 'position_ratio' => 0.1],
            ['name' => 'Vue.js', 'evidence' => 'both', 'position_ratio' => 0.2],
        ],
        'similarities' => ['title_cosine' => 0.8, 'responsibility_cosine' => 0.7],
        'signals' => ['experience' => 90, 'location' => 80, 'education' => 70, 'language' => 60],
        'cap_context' => ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
    ];
}

it('creates a profile seeded with the config ruleset', function (): void {
    $this->actingAs(studioAdmin())->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();

    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();

    expect($profile->rules['blend'])->toBe(['h' => 0.45, 's' => 0.25, 'd' => 0.30])
        ->and($profile->rules_version)->toBe(2);
});

it('ingests a posting with passing gates and dedupes on re-ingest', function (): void {
    $admin = studioAdmin();
    $this->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();
    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();

    $this->actingAs($admin)->post('/cv-studio/postings', studioPostingPayload($profile->uuid))->assertRedirect();
    $this->actingAs($admin)->post('/cv-studio/postings', studioPostingPayload($profile->uuid))->assertRedirect();

    $postings = StudioPostingEloquentModel::query()->get();

    expect($postings)->toHaveCount(1)
        ->and($postings->first()->remote_scope)->toBe('remote_eu')
        ->and($postings->first()->gateResults->every(fn ($result): bool => $result->passed))->toBeTrue();
});

it('scores and rescores deterministically from stored rows', function (): void {
    $admin = studioAdmin();
    $this->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();
    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();
    $this->actingAs($admin)->post('/cv-studio/postings', studioPostingPayload($profile->uuid))->assertRedirect();
    $posting = StudioPostingEloquentModel::query()->firstOrFail();

    $first = $this->actingAs($admin)->postJson("/cv-studio/postings/{$posting->uuid}/score", scoreInput())
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($admin)->postJson("/cv-studio/postings/{$posting->uuid}/rescore", scoreInput())
        ->assertOk()
        ->json('data');

    expect($second['total_score'])->toBe($first['total_score'])
        ->and($second['raw_score'])->toBe($first['raw_score'])
        ->and($first['heuristic_label'])->toContain('Heuristic')
        ->and($first['band'])->not->toBeNull();
});

it('refuses to score a gate-failed posting', function (): void {
    $admin = studioAdmin();
    $this->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();
    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();

    $payload = studioPostingPayload($profile->uuid);
    $payload['title'] = 'WordPress Developer';
    $payload['canonical_url'] = 'https://acme.com/jobs/wp-1';
    $payload['text'] = 'WordPress Elementor role, onsite.';
    $payload['location_text'] = 'Madrid office';

    $this->actingAs($admin)->post('/cv-studio/postings', $payload)->assertRedirect();
    $posting = StudioPostingEloquentModel::query()->where('title', 'WordPress Developer')->firstOrFail();

    expect($posting->gateResults->every(fn ($result): bool => $result->passed))->toBeFalse();

    $this->actingAs($admin)->postJson("/cv-studio/postings/{$posting->uuid}/score", scoreInput())->assertNotFound();
});

it('keeps a second profile separate with no schema change (SC-7)', function (): void {
    $admin = studioAdmin();
    $this->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();

    $this->actingAs($admin)->post('/cv-studio/profiles', [
        ...studioProfilePayload(),
        'name' => 'Gap style',
        'slug' => 'gap-style',
        'accepted_remote_scopes' => ['remote_pt_es'],
        'stack_must' => ['React'],
    ])->assertRedirect();

    $gap = StudioProfileEloquentModel::query()->where('slug', 'gap-style')->firstOrFail();
    $gap->update(['rules' => [...config('cv-job-studio'), 'blend' => ['h' => 0.25, 's' => 0.25, 'd' => 0.50]]]);

    $payload = studioPostingPayload($gap->uuid);
    $this->actingAs($admin)->post('/cv-studio/postings', $payload)->assertRedirect();

    $posting = StudioPostingEloquentModel::query()->where('profile_id', $gap->id)->firstOrFail();

    // remote_eu is not accepted by the gap profile: gates do not leak across profiles.
    expect($posting->gateResults->firstWhere('gate_code', 'G1')->passed)->toBeFalse()
        ->and($gap->rules['blend'])->toBe(['h' => 0.25, 's' => 0.25, 'd' => 0.50]);
});

it('exports csv, xlsx and pdf', function (): void {
    $admin = studioAdmin();
    $this->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();
    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();
    $this->actingAs($admin)->post('/cv-studio/postings', studioPostingPayload($profile->uuid))->assertRedirect();

    $this->actingAs($admin)->get('/cv-studio/postings/export?format=csv')->assertOk();
    $this->actingAs($admin)->get('/cv-studio/postings/export?format=xlsx')->assertOk();
    $this->actingAs($admin)->get('/cv-studio/postings/export?format=pdf')->assertOk();
});

it('bulk deletes and restores as a pair', function (): void {
    $admin = studioAdmin();
    $this->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();
    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();

    $first = studioPostingPayload($profile->uuid);
    $second = studioPostingPayload($profile->uuid);
    $second['canonical_url'] = 'https://boards.greenhouse.io/acme/jobs/4243';

    $this->actingAs($admin)->post('/cv-studio/postings', $first)->assertRedirect();
    $this->actingAs($admin)->post('/cv-studio/postings', $second)->assertRedirect();

    $uuids = StudioPostingEloquentModel::query()->pluck('uuid')->all();

    $this->actingAs($admin)->post('/cv-studio/postings/bulk-delete', ['uuids' => $uuids])->assertRedirect();
    expect(StudioPostingEloquentModel::query()->count())->toBe(0);

    $this->actingAs($admin)->post('/cv-studio/postings/bulk-restore', ['uuids' => $uuids])->assertRedirect();
    expect(StudioPostingEloquentModel::query()->count())->toBe(2);
});

it('rejects unauthenticated, cross-user and invalid input', function (): void {
    $this->get('/cv-studio/postings')->assertRedirect('/login');

    $admin = studioAdmin();
    $this->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();
    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();
    $this->actingAs($admin)->post('/cv-studio/postings', studioPostingPayload($profile->uuid))->assertRedirect();
    $posting = StudioPostingEloquentModel::query()->firstOrFail();

    $other = User::factory()->create();
    $other->assignRole('SUPER_ADMIN');

    $this->actingAs($other)->getJson("/cv-studio/postings/{$posting->uuid}")->assertNotFound();

    $this->actingAs($admin)->post('/cv-studio/postings', ['title' => 'Missing fields'])->assertSessionHasErrors();
});
