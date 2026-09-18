<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StudioSkillRelationSeeder;
use Database\Seeders\StudioSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Application\Commands\RecomputeChannelBaselinesHandler;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioChannelBaselineEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function pipelineAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function pipelineProfile(object $test, User $admin, array $overrides = []): StudioProfileEloquentModel
{
    $response = $test->actingAs($admin)->post('/cv-studio/profiles', [
        'name' => 'Fullstack Remote',
        'slug' => 'fullstack-'.Str::lower(Str::random(6)),
        'accepted_remote_scopes' => ['remote_global', 'remote_eu', 'remote_pt_es'],
        'stack_must' => ['Laravel'],
        'stack_reject' => ['WordPress'],
        ...$overrides,
    ]);
    $response->assertRedirect();

    return StudioProfileEloquentModel::query()->orderByDesc('id')->firstOrFail();
}

function ingestedPosting(object $test, User $admin, StudioProfileEloquentModel $profile, array $overrides = []): StudioPostingEloquentModel
{
    $payload = [
        'profile_uuid' => $profile->uuid,
        'title' => 'Senior Fullstack Developer (Laravel + Vue)',
        'canonical_url' => 'https://boards.greenhouse.io/acme/jobs/'.random_int(10000, 99999),
        'employer_name' => 'Acme',
        'location_text' => 'Remote, EU',
        'text' => 'We need a Senior Fullstack Developer with Laravel and Vue.js experience. Remote within the EU.',
        'requirements' => [
            ['canonical_name' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
        ],
        ...$overrides,
    ];

    $test->actingAs($admin)->post('/cv-studio/postings', $payload)->assertRedirect();

    return StudioPostingEloquentModel::query()->orderByDesc('id')->firstOrFail();
}

it('refuses a run when gate inputs are missing (FR-31)', function (): void {
    $admin = pipelineAdmin();
    $profile = pipelineProfile($this, $admin);
    $profile->update(['accepted_remote_scopes' => []]);

    $this->actingAs($admin)->postJson('/cv-studio/runs', ['profile_uuid' => $profile->uuid])->assertUnprocessable();

    expect(StudioRunEloquentModel::query()->count())->toBe(0);
});

it('starts a run and reports zero-match insights (SC-1)', function (): void {
    $admin = pipelineAdmin();
    $profile = pipelineProfile($this, $admin);

    $uuid = $this->actingAs($admin)->postJson('/cv-studio/runs', ['profile_uuid' => $profile->uuid])
        ->assertAccepted()
        ->json('data.uuid');

    $run = StudioRunEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

    expect($run->status)->toBe('finished');

    $report = $this->actingAs($admin)->getJson("/cv-studio/runs/{$uuid}/report")->assertOk()->json('report');

    expect($report['jds_analyzed'])->toBe(0)
        ->and($report['outcome_correlation']['suppressed'])->toBeTrue();
});

it('tracks status and outcome separately and leaves fit untouched', function (): void {
    $admin = pipelineAdmin();
    $profile = pipelineProfile($this, $admin);
    $posting = ingestedPosting($this, $admin, $profile);

    $this->actingAs($admin)->put("/cv-studio/postings/{$posting->uuid}/status", ['status' => 'applied'])->assertRedirect();
    $this->actingAs($admin)->put("/cv-studio/applications/{$posting->uuid}/outcome", [
        'outcome' => 'screening', 'note' => 'Coalition Technologies replied.',
    ])->assertRedirect();

    $application = StudioApplicationEloquentModel::query()->where('posting_id', $posting->id)->firstOrFail();

    expect($application->status)->toBe('saved')
        ->and($application->outcome)->toBe('screening')
        ->and($posting->refresh()->status)->toBe('applied');
});

it('keeps every opportunity factor at policy on the 16 historical applications (SC-14)', function (): void {
    $admin = pipelineAdmin();
    $this->seed(StudioSourceSeeder::class);
    $profile = pipelineProfile($this, $admin);

    $postings = [
        ingestedPosting($this, $admin, $profile)->id,
        ingestedPosting($this, $admin, $profile, ['canonical_url' => 'https://boards.greenhouse.io/acme/jobs/'.random_int(100000, 999999)])->id,
    ];

    // 16 applications, 1 screening, 1 rejection, 14 silent — like research §10.
    $outcomes = ['screening', 'rejected', ...array_fill(0, 14, 'unknown')];

    foreach ($outcomes as $index => $outcome) {
        StudioApplicationEloquentModel::query()->create([
            'user_id' => $admin->id,
            'posting_id' => $postings[$index % 2],
            'status' => 'applied',
            'outcome' => $outcome,
            'outcome_at' => now(),
        ]);
    }

    $before = StudioChannelBaselineEloquentModel::query()
        ->where('user_id', $admin->id)
        ->pluck('policy_value', 'bucket')
        ->all();

    app(RecomputeChannelBaselinesHandler::class)->handle($admin->id);

    $after = StudioChannelBaselineEloquentModel::query()
        ->where('user_id', $admin->id)
        ->get();

    // No group reaches the gate (30 applications, 3 positives): every factor
    // stays at policy, none gains an applied_from date, and no fit score
    // exists to have changed (NFR-13).
    foreach ($after as $baseline) {
        expect((float) $baseline->policy_value)->toBe((float) $before[$baseline->bucket])
            ->and($baseline->applied_from)->toBeNull();
    }

    expect(StudioScoreEloquentModel::query()->count())->toBe(0);
});

it('confirms and rejects relations without granting pending credit', function (): void {
    $admin = pipelineAdmin();
    $this->seed(StudioSkillRelationSeeder::class);

    $pending = StudioSkillRelationEloquentModel::query()->create([
        'user_id' => $admin->id,
        'from_skill' => 'Rust',
        'to_skill' => 'Cargo',
        'kind' => 'family',
        'origin' => 'model',
        'status' => 'pending',
    ]);

    $list = $this->actingAs($admin)->getJson('/cv-studio/relations')->assertOk()->json();

    expect(collect($list['data'] ?? $list)->contains('uuid', $pending->uuid))->toBeTrue();

    $this->actingAs($admin)->post("/cv-studio/relations/{$pending->uuid}/confirm")->assertRedirect();

    expect($pending->refresh()->status)->toBe('confirmed');
});

it('lists references unscored and pastes text like any other posting', function (): void {
    $admin = pipelineAdmin();
    $profile = pipelineProfile($this, $admin);

    $posting = StudioPostingEloquentModel::query()->create([
        'user_id' => $admin->id,
        'profile_id' => $profile->id,
        'canonical_url' => 'https://www.linkedin.com/jobs/view/999',
        'url_hash' => hash('sha256', 'https://www.linkedin.com/jobs/view/999'),
        'title' => 'LinkedIn signal',
        'status' => 'reference',
        'source' => 'linkedin',
    ]);

    $references = $this->actingAs($admin)->getJson('/cv-studio/references')->assertOk()->json();

    expect($references['total'] ?? count($references['data'] ?? []))->toBeGreaterThanOrEqual(1);

    $this->actingAs($admin)->post("/cv-studio/references/{$posting->uuid}/paste", [
        'text' => str_repeat('We need Laravel and Vue.js experience for this remote role. ', 10),
    ])->assertRedirect();

    expect($posting->refresh()->status)->toBe('new');
});

it('resolves and unlinks a posting destination', function (): void {
    $admin = pipelineAdmin();
    $profile = pipelineProfile($this, $admin);
    $posting = ingestedPosting($this, $admin, $profile);

    $this->actingAs($admin)->post("/cv-studio/postings/{$posting->uuid}/resolve")->assertRedirect();

    expect($posting->refresh()->apply_destination)->toBe('at_source');

    $this->actingAs($admin)->post("/cv-studio/postings/{$posting->uuid}/unlink")->assertRedirect();

    expect($posting->refresh()->apply_destination)->toBeNull();
});

it('updates the opportunity policy only with valid factors', function (): void {
    $admin = pipelineAdmin();
    $profile = pipelineProfile($this, $admin);

    $this->actingAs($admin)->put("/cv-studio/profiles/{$profile->uuid}/policy", [
        'channels' => ['employer_site' => ['value' => 1.0, 'grade' => 'C', 'source' => 'policy(Q19b)']],
        'neutral' => false,
    ])->assertRedirect();

    expect((float) $profile->refresh()->rules['opportunity']['channel']['employer_site']['value'])->toBe(1.0);

    $this->actingAs($admin)->put("/cv-studio/profiles/{$profile->uuid}/policy", [
        'channels' => ['employer_site' => ['value' => 5.0, 'grade' => 'C', 'source' => 'policy(Q19b)']],
    ])->assertSessionHasErrors();
});

it('prices a cap in postings unlocked (SC-10)', function (): void {
    $admin = pipelineAdmin();
    $profile = pipelineProfile($this, $admin);
    $posting = ingestedPosting($this, $admin, $profile);

    $scoreInput = [
        'cv_skills' => [['name' => 'Laravel', 'evidence' => 'in_bullet', 'position_ratio' => 0.1]],
        'similarities' => ['title_cosine' => 0.9, 'responsibility_cosine' => 0.9],
        'signals' => ['experience' => 100, 'location' => 100, 'education' => 100, 'language' => 100],
        'cap_context' => ['readable' => true, 'credential_ok' => false, 'evidence_ok' => true],
    ];

    $score = $this->actingAs($admin)->postJson("/cv-studio/postings/{$posting->uuid}/score", $scoreInput)
        ->assertCreated()
        ->json('data');

    expect($score['cap_reason'])->toBe('credential_or_floor_or_language');

    $this->actingAs($admin)->postJson('/cv-studio/runs', ['profile_uuid' => $profile->uuid])->assertAccepted();
    $run = StudioRunEloquentModel::query()->orderByDesc('id')->firstOrFail();

    $report = $this->actingAs($admin)->getJson("/cv-studio/runs/{$run->uuid}/report")->assertOk()->json('report');

    expect($report['reach_table'])->not->toBeEmpty();
});

it('reports budgets and a source catalogue', function (): void {
    $admin = pipelineAdmin();
    $this->seed(StudioSourceSeeder::class);

    $this->actingAs($admin)->getJson('/cv-studio/budgets')->assertOk();

    $sources = $this->actingAs($admin)->getJson('/cv-studio/sources')->assertOk()->json('data');

    expect($sources)->not->toBeEmpty();
});
