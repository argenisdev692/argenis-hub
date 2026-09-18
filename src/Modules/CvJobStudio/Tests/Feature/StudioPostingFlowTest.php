<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Http\Export\StudioPostingExportTransformer;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

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

/**
 * Two ingested postings for one admin; the first one scored.
 *
 * @return array{0: User, 1: StudioPostingEloquentModel, 2: StudioPostingEloquentModel}
 */
function studioTwoPostings(object $test): array
{
    $admin = studioAdmin();
    $test->actingAs($admin)->post('/cv-studio/profiles', studioProfilePayload())->assertRedirect();
    $profile = StudioProfileEloquentModel::query()->where('slug', 'fullstack')->firstOrFail();

    $second = studioPostingPayload($profile->uuid);
    $second['title'] = 'Another Laravel Role';
    $second['canonical_url'] = 'https://boards.greenhouse.io/acme/jobs/4243';

    $test->actingAs($admin)->post('/cv-studio/postings', studioPostingPayload($profile->uuid))->assertRedirect();
    $test->actingAs($admin)->post('/cv-studio/postings', $second)->assertRedirect();

    $scored = StudioPostingEloquentModel::query()->where('canonical_url', 'like', '%4242')->firstOrFail();
    $unscored = StudioPostingEloquentModel::query()->where('canonical_url', 'like', '%4243')->firstOrFail();

    $test->actingAs($admin)->postJson("/cv-studio/postings/{$scored->uuid}/score", scoreInput())->assertCreated();

    return [$admin, $scored, $unscored];
}

it('renders the postings page shell without running the list query', function (): void {
    $this->withoutVite()->actingAs(studioAdmin())->get('/cv-studio/postings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('cv-studio/Postings/Index')->missing('postings'));
});

it('lists postings with the latest score so the fit column is populated', function (): void {
    [$admin, $scored] = studioTwoPostings($this);

    $rows = collect($this->actingAs($admin)->getJson('/cv-studio/postings')->assertOk()->json('data'));
    $row = $rows->firstWhere('uuid', $scored->uuid);

    expect($row['band'])->not->toBeNull()
        ->and($row['total_score'])->toBeNumeric();
});

it('sorts by fit with unscored postings last in both directions', function (): void {
    [$admin, $scored, $unscored] = studioTwoPostings($this);

    foreach ([-1, 1] as $order) {
        $uuids = $this->actingAs($admin)
            ->getJson("/cv-studio/postings?sort_field=fit&sort_order={$order}")
            ->assertOk()
            ->json('data.*.uuid');

        expect($uuids)->toBe([$scored->uuid, $unscored->uuid]);
    }

    $this->actingAs($admin)->getJson('/cv-studio/postings?sort_field=password')->assertUnprocessable();
});

it('filters by pipeline stage and changes stage over JSON', function (): void {
    [$admin, $scored, $unscored] = studioTwoPostings($this);

    $this->actingAs($admin)
        ->putJson("/cv-studio/postings/{$scored->uuid}/status", ['status' => 'saved'])
        ->assertOk()
        ->assertJson(['status' => 'saved']);

    $uuids = $this->actingAs($admin)->getJson('/cv-studio/postings?stages[]=saved')->assertOk()->json('data.*.uuid');

    expect($uuids)->toBe([$scored->uuid]);

    $this->actingAs($admin)->getJson('/cv-studio/postings?stages[]=hacked')->assertUnprocessable();
});

it('answers JSON writes with JSON instead of a redirect a fetch client cannot follow', function (): void {
    [$admin, $scored] = studioTwoPostings($this);

    $this->actingAs($admin)->deleteJson("/cv-studio/postings/{$scored->uuid}")->assertOk()->assertJsonStructure(['message']);
    $this->actingAs($admin)->patchJson("/cv-studio/postings/{$scored->uuid}/restore")->assertOk();
    $this->actingAs($admin)
        ->postJson('/cv-studio/postings/bulk-delete', ['uuids' => [$scored->uuid]])
        ->assertOk()
        ->assertJson(['count' => 1]);
});

it('shows, lists and exports the newest score when a posting has several', function (): void {
    [$admin, $scored] = studioTwoPostings($this);

    // The original score becomes the older one; a newer row is inserted after
    // it (higher id), so an unordered `scores->first()` would pick the stale one.
    $older = StudioScoreEloquentModel::query()->where('posting_id', $scored->id)->firstOrFail();
    $older->forceFill(['computed_at' => now()->subDay(), 'band' => 'strong', 'total_score' => 91])->save();

    $newer = $older->replicate();
    $newer->forceFill([
        'uuid' => (string) Str::uuid(),
        'computed_at' => now(),
        'band' => 'skip',
        'total_score' => 12.5,
    ])->save();

    $show = $this->actingAs($admin)->getJson("/cv-studio/postings/{$scored->uuid}")->assertOk();

    expect($show->json('score.band'))->toBe('skip')
        ->and($show->json('posting.band'))->toBe('skip');

    $listed = collect($this->actingAs($admin)->getJson('/cv-studio/postings')->json('data'))
        ->firstWhere('uuid', $scored->uuid);

    expect($listed['band'])->toBe('skip');

    $exported = StudioPostingExportTransformer::transformForExcel(
        StudioPostingEloquentModel::query()->with('scores')->findOrFail($scored->id),
    );

    expect($exported['Band'])->toBe('skip');
});
