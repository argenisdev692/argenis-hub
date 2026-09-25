<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Application\Commands\BuildInsightReportHandler;
use Modules\CvJobStudio\Domain\Services\InsightRecommender;
use Modules\CvJobStudio\Domain\Services\RequirementFrequencyCounter;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillMatchEloquentModel;

/*
| The insight report used to render empty no matter what was stored: the
| repository deduplicated requirement names before counting, so every row came
| back with count 1, `jds_analyzed` counted names instead of jobs, `covered` was
| hardcoded false and `recommendations` was a literal empty array. These tests
| pin the four fixes against real rows (FR-26, FR-8, NFR-1).
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function insightOwner(): User
{
    $owner = User::factory()->create();
    $owner->assignRole('SUPER_ADMIN');

    return $owner;
}

function insightProfile(User $owner): StudioProfileEloquentModel
{
    return StudioProfileEloquentModel::query()->create([
        'user_id' => $owner->id,
        'name' => 'Fullstack',
        'slug' => 'fullstack-'.Str::lower(Str::random(6)),
        'flow' => 'fullstack',
        'is_active' => true,
        'accepted_remote_scopes' => ['remote_eu'],
        'stack_must' => ['Laravel', 'Vue.js'],
        'stack_reject' => [],
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);
}

/**
 * A posting with its requirement rows, the shape the pipeline stores: one row
 * per requirement mention, `tag`/`nature` engine-validated.
 */
function insightPostingWith(User $owner, StudioProfileEloquentModel $profile, string $suffix, array $requirements): StudioPostingEloquentModel
{
    $url = "https://boards.greenhouse.io/acme/jobs/{$suffix}";

    $posting = StudioPostingEloquentModel::query()->create([
        'user_id' => $owner->id,
        'profile_id' => $profile->id,
        'canonical_url' => $url,
        'url_hash' => hash('sha256', $url),
        'title' => 'Senior Developer',
        'employer_name' => 'Acme',
        'remote_scope' => 'remote_eu',
        'status' => 'new',
    ]);

    foreach ($requirements as $name => $times) {
        for ($i = 0; $i < $times; $i++) {
            StudioRequirementEloquentModel::query()->create([
                'user_id' => $owner->id,
                'posting_id' => $posting->id,
                'canonical_name' => $name,
                'raw_text' => $name,
                'tag' => 'required',
                'nature' => 'hard',
            ]);
        }
    }

    return $posting;
}

/**
 * A stored score plus the skill matches that decide coverage: only credit
 * above zero means the CV evidences the requirement.
 */
function insightScoreCovering(User $owner, StudioPostingEloquentModel $posting, array $credits): void
{
    $score = StudioScoreEloquentModel::query()->create([
        'user_id' => $owner->id,
        'posting_id' => $posting->id,
        'h' => 80,
        's' => 70,
        'd' => 90,
        'h_components' => [],
        's_components' => [],
        'd_components' => [],
        'raw_score' => 78.0,
        'total_score' => 78.0,
        'band' => 'good',
        'rules_version' => 2,
    ]);

    foreach ($credits as $name => $credit) {
        $requirement = StudioRequirementEloquentModel::query()
            ->where('user_id', $owner->id)
            ->where('posting_id', $posting->id)
            ->where('canonical_name', $name)
            ->firstOrFail();

        StudioSkillMatchEloquentModel::query()->create([
            'user_id' => $owner->id,
            'score_id' => $score->id,
            'requirement_id' => $requirement->id,
            'credit_final' => $credit,
        ]);
    }
}

it('counts requirement frequency per posting instead of per distinct name (FR-26)', function (): void {
    $owner = insightOwner();
    $profile = insightProfile($owner);

    // Laravel is mentioned twice in the first job and once in each other one.
    $first = insightPostingWith($owner, $profile, 'freq-1', ['Laravel' => 2]);
    insightPostingWith($owner, $profile, 'freq-2', ['Laravel' => 1, 'Vue.js' => 1]);
    insightPostingWith($owner, $profile, 'freq-3', ['Laravel' => 1, 'Vue.js' => 1]);

    insightScoreCovering($owner, $first, ['Laravel' => 0.9]);

    $run = StudioRunEloquentModel::query()->create([
        'user_id' => $owner->id,
        'profile_id' => $profile->id,
        'status' => 'scored',
        'rules_version' => 2,
    ]);

    $report = app(BuildInsightReportHandler::class)->handle($run->uuid, $owner->id);
    $rows = collect($report->requirements['requirements'])->keyBy('name');

    // Three postings analysed, not four requirement rows and not two names.
    expect($report->jdsAnalyzed)->toBe(3)
        ->and($report->requirements['total'])->toBe(3);

    // Laravel: asked for by all three postings (mentioned four times in
    // total), and covered because a stored score gave it credit.
    expect($rows['Laravel']['count'])->toBe(3)
        ->and($rows['Laravel']['covered'])->toBeTrue();

    // Vue.js: two of the three postings, and never matched by a score.
    expect($rows['Vue.js']['count'])->toBe(2)
        ->and($rows['Vue.js']['covered'])->toBeFalse();

    // Three postings is under the 30-job gate, so the report carries counts
    // without percentages and says why.
    expect($rows['Laravel']['percentage'])->toBeNull()
        ->and($report->sampleNote)->toContain('30');
});

it('expresses the percentage over postings once the sample supports it (FR-26)', function (): void {
    $rows = (new RequirementFrequencyCounter)->count([
        ['posting_id' => 1, 'canonical_name' => 'Laravel', 'covered' => true],
        ['posting_id' => 2, 'canonical_name' => 'Laravel', 'covered' => true],
        ['posting_id' => 3, 'canonical_name' => 'Laravel', 'covered' => true],
        ['posting_id' => 1, 'canonical_name' => 'Laravel', 'covered' => true],
        ['posting_id' => 2, 'canonical_name' => 'Vue.js', 'covered' => false],
    ], 2);

    // The repeat mention inside posting 1 counts once: frequency is a property
    // of postings, not of how often a posting repeats itself.
    expect($rows['total'])->toBe(3)
        ->and($rows['requirements'][0])->toBe(['name' => 'Laravel', 'count' => 3, 'covered' => true, 'percentage' => 100.0])
        ->and($rows['requirements'][1])->toBe(['name' => 'Vue.js', 'count' => 1, 'covered' => false, 'percentage' => 33.3]);
});

it('suppresses percentages under the 30-posting gate but keeps the counts (FR-26)', function (): void {
    $rows = (new RequirementFrequencyCounter)->count([
        ['posting_id' => 1, 'canonical_name' => 'Laravel', 'covered' => true],
        ['posting_id' => 2, 'canonical_name' => 'Laravel', 'covered' => true],
    ], 30);

    expect($rows['total'])->toBe(2)
        ->and($rows['requirements'][0]['count'])->toBe(2)
        ->and($rows['requirements'][0]['percentage'])->toBeNull();
});

it('recommends only requirements the CV already evidences (FR-8)', function (): void {
    $recommender = new InsightRecommender;

    $frequency = [
        'total' => 12,
        'requirements' => [
            ['name' => 'Laravel', 'count' => 9, 'covered' => true, 'percentage' => 75.0],
            ['name' => 'Vue.js', 'count' => 6, 'covered' => true, 'percentage' => 50.0],
            ['name' => 'Kubernetes', 'count' => 8, 'covered' => false, 'percentage' => 66.7],
            ['name' => 'GraphQL', 'count' => 4, 'covered' => false, 'percentage' => 33.3],
        ],
    ];

    $recommendations = $recommender->recommend($frequency);

    // Kubernetes is the second most frequent requirement and is absent from
    // the report entirely: the recommender may not ask for a missing skill.
    expect($recommendations)->toHaveCount(2)
        ->and(implode(' ', $recommendations))->toContain('Laravel')
        ->and(implode(' ', $recommendations))->not->toContain('Kubernetes')
        ->and($recommendations[0])->toContain('9 of the 12');
});

it('never recommends a one-off requirement and caps the list at five', function (): void {
    $requirements = [];

    for ($i = 1; $i <= 8; $i++) {
        $requirements[] = ['name' => "Skill {$i}", 'count' => 4, 'covered' => true, 'percentage' => 20.0];
    }

    $requirements[] = ['name' => 'Lonely', 'count' => 1, 'covered' => true, 'percentage' => 5.0];

    $recommendations = (new InsightRecommender)->recommend(['total' => 20, 'requirements' => $requirements]);

    expect($recommendations)->toHaveCount(5)
        ->and(implode(' ', $recommendations))->not->toContain('Lonely');
});

it('produces a report with real content for a run whose postings were scored', function (): void {
    $owner = insightOwner();
    $profile = insightProfile($owner);

    $first = insightPostingWith($owner, $profile, 'report-1', ['Laravel' => 1, 'Vue.js' => 1]);
    $second = insightPostingWith($owner, $profile, 'report-2', ['Laravel' => 1, 'Vue.js' => 1]);
    insightScoreCovering($owner, $first, ['Laravel' => 0.8]);
    insightScoreCovering($owner, $second, ['Laravel' => 0.7]);

    $run = StudioRunEloquentModel::query()->create([
        'user_id' => $owner->id,
        'profile_id' => $profile->id,
        'status' => 'scored',
        'rules_version' => 2,
    ]);

    $report = app(BuildInsightReportHandler::class)->handle($run->uuid, $owner->id);

    expect($report->jdsAnalyzed)->toBe(2)
        ->and($report->requirements['requirements'])->not->toBeEmpty()
        ->and($report->recommendations)->not->toBeEmpty();
});
