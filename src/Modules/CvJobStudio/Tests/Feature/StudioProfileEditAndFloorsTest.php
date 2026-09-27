<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Domain\Enums\GateCode;
use Modules\CvJobStudio\Domain\Services\GateEvaluator;
use Modules\CvJobStudio\Domain\Services\SkillRelationSet;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

/*
| Three fixes, each pinned against the defect it removes.
|
| 1. The profile could only be changed through `upsert()` matching on the slug
|    the form submitted, so editing meant retyping the exact slug and a changed
|    character created a duplicate. `PUT /profiles/{uuid}` now owns the address.
| 2. `skill_floors` is a shortlist FILTER, deliberately not wired into
|    `creditFor()`: the score is calibrated, and a filter can only remove a
|    candidate from consideration.
| 3. Requirements with an invalid tag/nature were dropped with nothing in the
|    log, so a model drifting from the enum contract looked like an empty
|    report.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function profileOwner(): User
{
    $owner = User::factory()->create();
    $owner->assignRole('SUPER_ADMIN');

    return $owner;
}

function storedProfile(User $owner, array $overrides = []): StudioProfileEloquentModel
{
    return StudioProfileEloquentModel::query()->create([
        'user_id' => $owner->id,
        'name' => $overrides['name'] ?? 'Fullstack',
        'slug' => $overrides['slug'] ?? 'fullstack-'.Str::lower(Str::random(6)),
        'flow' => 'fullstack',
        'is_active' => true,
        'accepted_remote_scopes' => $overrides['accepted_remote_scopes'] ?? ['remote_eu'],
        'stack_must' => $overrides['stack_must'] ?? ['Laravel'],
        'stack_reject' => $overrides['stack_reject'] ?? [],
        'skill_floors' => $overrides['skill_floors'] ?? [],
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);
}

// ── Fix 1: the profile is editable ─────────────────────────────────────────

it('edits an existing profile by uuid without creating a second one', function (): void {
    $owner = profileOwner();
    $profile = storedProfile($owner, ['stack_must' => ['Laravel'], 'years_baseline' => 4]);

    $this->actingAs($owner)
        ->putJson("/cv-studio/profiles/{$profile->uuid}", [
            'name' => 'Fullstack · Laravel + React',
            'slug' => $profile->slug,
            'stack_must' => ['Laravel', 'React'],
            'years_baseline' => 5,
        ])
        ->assertRedirect();

    $profile->refresh();

    expect(StudioProfileEloquentModel::query()->where('user_id', $owner->id)->count())->toBe(1)
        ->and($profile->name)->toBe('Fullstack · Laravel + React')
        ->and($profile->stack_must)->toBe(['Laravel', 'React'])
        ->and($profile->years_baseline)->toBe(5);
});

it('lets the slug change, which was impossible while upsert matched on it', function (): void {
    $owner = profileOwner();
    $profile = storedProfile($owner, ['slug' => 'fullstack-laravel-vue-remote']);

    $this->actingAs($owner)->putJson("/cv-studio/profiles/{$profile->uuid}", [
        'name' => 'Fullstack',
        'slug' => 'fullstack-laravel-react-remote',
        'stack_must' => ['Laravel', 'React'],
    ])->assertRedirect();

    expect($profile->refresh()->slug)->toBe('fullstack-laravel-react-remote')
        ->and(StudioProfileEloquentModel::query()->where('user_id', $owner->id)->count())->toBe(1);
});

it('moves the profile holding the old slug instead of failing on a unique index', function (): void {
    $owner = profileOwner();
    $mover = storedProfile($owner, ['slug' => 'target-slug']);
    storedProfile($owner, ['slug' => 'incumbent']);

    $this->actingAs($owner)->putJson("/cv-studio/profiles/{$mover->uuid}", [
        'name' => 'Fullstack',
        'slug' => 'incumbent',
        'stack_must' => ['Laravel'],
    ])->assertRedirect();

    expect(StudioProfileEloquentModel::query()->where('user_id', $owner->id)->count())->toBe(2)
        ->and($mover->refresh()->slug)->toBe('incumbent');
});

it('refuses to edit a profile that is not the caller\'s', function (): void {
    $owner = profileOwner();
    $other = profileOwner();
    $profile = storedProfile($other);

    $this->actingAs($owner)
        ->putJson("/cv-studio/profiles/{$profile->uuid}", [
            'name' => 'Stolen',
            'slug' => 'stolen',
        ])
        ->assertNotFound();

    expect($profile->refresh()->name)->toBe('Fullstack');
});

it('validates a skill level and rejects an out-of-range one', function (): void {
    $owner = profileOwner();
    $profile = storedProfile($owner);

    $this->actingAs($owner)->putJson("/cv-studio/profiles/{$profile->uuid}", [
        'name' => 'Fullstack',
        'slug' => $profile->slug,
        'skill_floors' => ['Laravel' => 9],
    ])->assertUnprocessable();

    expect($profile->refresh()->skill_floors)->toBe([]);
});

// ── Fix 2: skill_floors as a shortlist filter ─────────────────────────────

function gateWith(array $floors, array $requirements): array
{
    $verdicts = (new GateEvaluator)->evaluate(
        [
            'remote_scope' => 'remote_eu',
            'title' => 'Senior Backend Developer',
            'text' => 'We need Laravel and React experience.',
            'url' => 'https://boards.greenhouse.io/acme/jobs/1',
            'requirements' => $requirements,
        ],
        [
            'accepted_remote_scopes' => ['remote_eu'],
            'stack_must' => ['Laravel'],
            'stack_reject' => [],
            'skill_floors' => $floors,
        ],
    );

    foreach ($verdicts as $verdict) {
        if ($verdict->gate === GateCode::SkillFloor) {
            return ['passed' => $verdict->passed, 'reason' => $verdict->reasonCode];
        }
    }

    return ['passed' => true, 'reason' => null];
}

it('passes a job whose core names an anchor skill', function (): void {
    // Laravel is the anchor; the job's required requirements include it even
    // though it also asks for React, which is only a mid skill.
    $result = gateWith(
        ['Laravel' => 4, 'React' => 2],
        [
            ['canonical_name' => 'Laravel', 'tag' => 'required'],
            ['canonical_name' => 'React', 'tag' => 'required'],
        ],
    );

    expect($result['passed'])->toBeTrue();
});

it('skips a job whose core sits entirely below the declared anchors', function (): void {
    // The asymmetry the profile actually has: four years of Laravel, a frontend
    // still on its second year. A job built around React is worth skipping even
    // though it would still land mid-band on score.
    $result = gateWith(
        ['Laravel' => 4, 'React' => 2],
        [
            ['canonical_name' => 'React', 'tag' => 'required'],
            ['canonical_name' => 'Vue', 'tag' => 'required'],
        ],
    );

    expect($result['passed'])->toBeFalse()
        ->and($result['reason'])->toBe('anchor_missing');
});

it('stays silent when the posting states no hard core at all', function (): void {
    // Preferred and bonus requirements describe a job's upside, not its core.
    // A posting whose only skills are optional states no core to disagree with,
    // so the filter has nothing to filter on and must not start excluding.
    $result = gateWith(
        ['Laravel' => 4, 'React' => 2],
        [
            ['canonical_name' => 'React', 'tag' => 'preferred'],
            ['canonical_name' => 'Laravel', 'tag' => 'bonus'],
        ],
    );

    expect($result['passed'])->toBeTrue();
});

it('still blocks when a real required skill sits below the anchors', function (): void {
    // The optional React/Laravel rows above must not be what makes the gate
    // pass: as soon as one genuinely required skill is below the floor, and
    // nothing required reaches an anchor, the job is skipped.
    $result = gateWith(
        ['Laravel' => 4, 'React' => 2],
        [
            ['canonical_name' => 'React', 'tag' => 'required'],
            ['canonical_name' => 'Laravel', 'tag' => 'bonus'],
        ],
    );

    expect($result['passed'])->toBeFalse();
});

it('stays silent when the candidate declared no anchors', function (): void {
    // The safe direction: a missing preference must never start blocking.
    expect(gateWith([], [['canonical_name' => 'React', 'tag' => 'required']])['passed'])->toBeTrue()
        ->and(gateWith(['React' => 2], [['canonical_name' => 'React', 'tag' => 'required']])['passed'])->toBeTrue();
});

it('stays silent when the posting states no required requirements', function (): void {
    // The gate also runs at ingest, before extraction has produced anything.
    expect(gateWith(['Laravel' => 4], [])['passed'])->toBeTrue();
});

it('matches aliases without matching a longer word', function (): void {
    $aliases = gateWith(
        ['Laravel' => 4],
        [['canonical_name' => 'Laravel', 'tag' => 'required']],
    );

    // "Reactive" must not satisfy a "React" anchor by substring.
    $reactive = gateWith(
        ['React' => 4],
        [['canonical_name' => 'Reactive', 'tag' => 'required']],
    );

    $exact = gateWith(
        ['React' => 4],
        [['canonical_name' => 'React', 'tag' => 'required']],
    );

    expect($aliases['passed'])->toBeTrue()
        ->and($reactive['passed'])->toBeFalse()
        ->and($exact['passed'])->toBeTrue();
});

it('ignores a requirement that is a sentence rather than a skill', function (): void {
    $result = gateWith(
        ['Laravel' => 4],
        [['canonical_name' => 'experience with distributed teams', 'tag' => 'required']],
    );

    // A phrase is not a name, so it matches no alias and names no core. The
    // posting's only requirement is unmatchable, which is missing evidence
    // rather than evidence of a mismatch - so it is not filtered.
    expect($result['passed'])->toBeTrue();
});

it('blocks on the matchable requirement even when a sentence sits beside it', function (): void {
    $result = gateWith(
        ['Laravel' => 4],
        [
            ['canonical_name' => 'experience with distributed teams', 'tag' => 'required'],
            ['canonical_name' => 'React', 'tag' => 'required'],
        ],
    );

    expect($result['passed'])->toBeFalse();
});

it('does not change a score: the filter only ever returns a verdict', function (): void {
    // The calibration (SC-2 recomputes from stored data, SC-3 holds the band
    // against a hand-judged set) depends on `creditFor()` being untouched.
    $reflection = new ReflectionClass(SkillRelationSet::class);
    $source = file_get_contents((string) $reflection->getFileName());

    expect($source)->not->toContain('skill_floors')
        ->and($source)->not->toContain('skillFloors');
});
