<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\CvJobStudio\Application\Commands\BuildInsightReportHandler;
use Modules\CvJobStudio\Application\Commands\RecordOutcomeHandler;
use Modules\CvJobStudio\Application\Commands\RefreshApplyPriorityHandler;
use Modules\CvJobStudio\Application\Queries\GetOwnRatesHandler;
use Modules\CvJobStudio\Domain\Ports\CvJudgePort;
use Modules\CvJobStudio\Domain\Ports\CvSourcePort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvStructureEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

/*
| Regression tests for the 2026-09-19 backend audit: hexagonal boundaries
| (no Eloquent / Infrastructure in Application or Domain), activity-log
| gaps from mass updates, silently-nulled list columns, raw-model
| responses, missing Inertia props, API bulk parity, the LLM rate limiter,
| the own-rates cache and the apply-priority N+1.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function boundaryOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('SUPER_ADMIN');

    return $user;
}

function boundaryProfile(User $owner): StudioProfileEloquentModel
{
    return StudioProfileEloquentModel::query()->create([
        'user_id' => $owner->id,
        'name' => 'Fullstack',
        'slug' => 'fullstack-'.Str::lower(Str::random(6)),
        'flow' => 'fullstack',
        'is_active' => true,
        'base_city' => 'Lisbon',
        'accepted_remote_scopes' => ['remote_eu'],
        'stack_must' => ['Laravel', 'Vue.js'],
        'stack_reject' => ['WordPress'],
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);
}

function boundaryPosting(User $owner, StudioProfileEloquentModel $profile, string $suffix = 'boundary'): StudioPostingEloquentModel
{
    return StudioPostingEloquentModel::query()->create([
        'user_id' => $owner->id,
        'profile_id' => $profile->id,
        'canonical_url' => "https://boards.greenhouse.io/acme/jobs/{$suffix}",
        'url_hash' => hash('sha256', "https://boards.greenhouse.io/acme/jobs/{$suffix}"),
        'title' => 'Senior Fullstack Developer',
        'remote_scope' => 'remote_eu',
        'status' => 'new',
        'discovery_channel' => 'employer_ats',
    ]);
}

function boundaryActivity(string $logName, string $event): int
{
    return DB::table('activity_log')->where('log_name', $logName)->where('event', $event)->count();
}

// ── Hexagonal boundaries ─────────────────────────────────────────────────

it('keeps Application and Domain free of Infrastructure, Eloquent and facades', function (): void {
    $violations = [];

    foreach (['Application', 'Domain'] as $layer) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path("src/Modules/CvJobStudio/{$layer}")));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach (['use Modules\\CvJobStudio\\Infrastructure\\', 'use Illuminate\\Database\\', 'use Illuminate\\Support\\Facades\\', '::query()'] as $forbidden) {
                if (str_contains($source, $forbidden)) {
                    $violations[] = "{$layer}/".basename($file->getPathname()).": {$forbidden}";
                }
            }
        }
    }

    expect($violations)->toBeEmpty();
});

// ── Audit trail (mass updates skipped LogsActivity) ──────────────────────

it('records relation confirmation in the activity log (T-091)', function (): void {
    $owner = boundaryOwner();
    $relation = StudioSkillRelationEloquentModel::query()->create([
        'user_id' => $owner->id, 'from_skill' => 'Laravel', 'to_skill' => 'PHP', 'kind' => 'implies', 'origin' => 'llm', 'status' => 'pending',
    ]);
    $before = boundaryActivity('studio.relations', 'updated');

    $this->actingAs($owner)->post("/cv-studio/relations/{$relation->uuid}/confirm")->assertRedirect();

    expect($relation->fresh()->status)->toBe('confirmed')
        ->and(boundaryActivity('studio.relations', 'updated'))->toBe($before + 1);
});

it('records posting status transitions in the activity log', function (): void {
    $owner = boundaryOwner();
    $posting = boundaryPosting($owner, boundaryProfile($owner));
    $before = boundaryActivity('studio.postings', 'updated');

    $this->actingAs($owner)->putJson("/cv-studio/postings/{$posting->uuid}/status", ['status' => 'saved'])->assertOk();

    expect($posting->fresh()->status)->toBe('saved')
        ->and(boundaryActivity('studio.postings', 'updated'))->toBe($before + 1);
});

it('records CV structure confirmation in the activity log without profile facts (RK-6)', function (): void {
    $owner = boundaryOwner();
    $structure = StudioCvStructureEloquentModel::query()->create([
        'user_id' => $owner->id, 'cv_id' => 1, 'source_text_hash' => str_repeat('a', 64), 'parser_version' => 'v2', 'profile_facts' => ['email' => 'me@example.com'],
    ]);

    $this->actingAs($owner)->put("/cv-studio/structures/{$structure->uuid}")->assertRedirect();

    $entry = DB::table('activity_log')->where('log_name', 'studio.cv_structures')->where('event', 'updated')->sole();

    expect($structure->fresh()->confirmed_at)->not->toBeNull()
        ->and((string) $entry->attribute_changes)->toContain('confirmed_at')
        ->and((string) $entry->attribute_changes)->not->toContain('me@example.com');
});

// ── Silently-nulled list columns ─────────────────────────────────────────

it('returns every profile gate input on the profiles list', function (): void {
    $owner = boundaryOwner();
    boundaryProfile($owner);

    $profile = $this->actingAs($owner)->getJson('/cv-studio/profiles')->assertOk()->json('data.0');

    expect($profile)->toMatchArray([
        'base_city' => 'Lisbon',
        'accepted_remote_scopes' => ['remote_eu'],
        'stack_must' => ['Laravel', 'Vue.js'],
        'stack_reject' => ['WordPress'],
    ]);
});

it('returns the canonical url on the postings list', function (): void {
    $owner = boundaryOwner();
    $posting = boundaryPosting($owner, boundaryProfile($owner));

    $row = $this->actingAs($owner)->getJson('/cv-studio/postings')->assertOk()->json('data.0');

    expect($row['uuid'])->toBe($posting->uuid)
        ->and($row['canonical_url'])->toBe('https://boards.greenhouse.io/acme/jobs/boundary');
});

// ── Response allowlists + Inertia props ──────────────────────────────────

it('serves the CV audit through a Data allowlist, never the raw model', function (): void {
    $owner = boundaryOwner();

    app()->instance(CvSourcePort::class, new class implements CvSourcePort
    {
        public function primaryForUser(int $userId): ?array
        {
            return ['cv_id' => 7, 'raw_text' => 'Senior Laravel developer.', 'is_primary' => true];
        }

        public function findForUser(string $cvUuid, int $userId): ?array
        {
            return $this->primaryForUser($userId);
        }
    });

    app()->instance(CvJudgePort::class, new class implements CvJudgePort
    {
        public function judge(string $cvText, ?string $targetJobTitle, int $userId): array
        {
            return [
                'verdict' => 'interview', 'verdict_reasons' => ['clear stack'], 'strengths' => ['APIs'], 'improvements' => [],
                'keyword_gaps' => [], 'xyz_gaps' => ['How many users?'], 'metric_questions' => [], 'provider' => 'fake', 'model' => 'fake',
            ];
        }
    });

    $audit = $this->actingAs($owner)->postJson('/cv-studio/cvs/audit', ['target_job_title' => 'Backend'])->assertCreated()->json('data');

    expect(array_keys($audit))->toBe(['uuid', 'verdict', 'verdict_reasons', 'target_job_title', 'strengths', 'improvements', 'keyword_gaps', 'xyz_gaps', 'created_at'])
        ->and($audit['verdict'])->toBe('interview');
});

it('serves the insight report through a Data allowlist and gives both run pages their uuid prop', function (): void {
    $owner = boundaryOwner();
    $profile = boundaryProfile($owner);
    $run = StudioRunEloquentModel::query()->create(['user_id' => $owner->id, 'profile_id' => $profile->id, 'status' => 'scored', 'rules_version' => 2]);

    (void) app(BuildInsightReportHandler::class)->handle($run->uuid, $owner->id);

    $this->actingAs($owner);

    $report = $this->getJson("/cv-studio/runs/{$run->uuid}/report")->assertOk()->json('report');

    expect($report)->not->toHaveKeys(['id', 'user_id', 'run_id'])
        ->and($report)->toHaveKeys(['uuid', 'jds_analyzed', 'requirements', 'reach_table', 'outcome_correlation']);

    $this->get("/cv-studio/runs/{$run->uuid}/report")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('cv-studio/Insights/Show')->where('uuid', $run->uuid));

    $this->get("/cv-studio/runs/{$run->uuid}")
        ->assertInertia(fn (AssertableInertia $page) => $page->component('cv-studio/Runs/Show')->where('uuid', $run->uuid));
});

// ── API parity + LLM rate limit ──────────────────────────────────────────

it('exposes bulk delete and restore on the Sanctum API', function (): void {
    $owner = boundaryOwner();
    $posting = boundaryPosting($owner, boundaryProfile($owner));

    $this->actingAs($owner, 'sanctum')->postJson('/api/cv-studio/postings/bulk-delete', ['uuids' => [$posting->uuid]])->assertOk()->assertJson(['count' => 1]);

    expect($posting->fresh()->trashed())->toBeTrue();

    $this->postJson('/api/cv-studio/postings/bulk-restore', ['uuids' => [$posting->uuid]])->assertOk()->assertJson(['count' => 1]);

    expect($posting->fresh()->trashed())->toBeFalse();
});

it('caps every model-calling route with the per-user LLM limiter (LLM10)', function (string $routeName): void {
    expect(RateLimiter::limiter('cv-studio-llm'))->not->toBeNull()
        ->and(Route::getRoutes()->getByName($routeName)?->gatherMiddleware())->toContain('throttle:cv-studio-llm');
})->with([
    'cv-studio.cvs.parse',
    'cv-studio.cvs.audit',
    'cv-studio.structures.rewrite',
    'cv-studio.postings.tailor',
    'cv-studio.runs.store',
    'api.cv-studio.cvs.parse',
    'api.cv-studio.cvs.audit',
    'api.cv-studio.runs.store',
]);

// ── Cache + N+1 ──────────────────────────────────────────────────────────

it('caches own rates per user and drops the cache when an outcome is recorded', function (): void {
    $owner = boundaryOwner();
    $posting = boundaryPosting($owner, boundaryProfile($owner));
    $key = GetOwnRatesHandler::cacheKey($owner->id);

    expect(app(GetOwnRatesHandler::class)->handle($owner->id))->toBe([])
        ->and(Cache::has($key))->toBeTrue();

    app(RecordOutcomeHandler::class)->handle($posting->uuid, 'interview', null, $owner->id);

    expect(Cache::has($key))->toBeFalse()
        ->and(app(GetOwnRatesHandler::class)->handle($owner->id)[0]->positives)->toBe(1);
});

it('refreshes apply priority with a constant number of queries (no N+1)', function (): void {
    $owner = boundaryOwner();
    $profile = boundaryProfile($owner);
    $this->actingAs($owner);

    $queriesFor = function (int $postings) use ($owner, $profile): int {
        foreach (range(1, $postings) as $index) {
            $posting = boundaryPosting($owner, $profile, Str::random(8)."-{$index}");
            $posting->requirements()->create(['user_id' => $owner->id, 'canonical_name' => 'Laravel', 'tag' => 'required', 'nature' => 'hard']);

            $this->postJson("/cv-studio/postings/{$posting->uuid}/score", [
                'cv_skills' => [['name' => 'Laravel', 'evidence' => 'in_bullet', 'position_ratio' => 0.1]],
                'similarities' => ['title_cosine' => 0.8, 'responsibility_cosine' => 0.7],
                'signals' => ['experience' => 90, 'location' => 80, 'education' => 70, 'language' => 60],
                'cap_context' => ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
            ])->assertCreated();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        (void) app(RefreshApplyPriorityHandler::class)->handle($owner->id, true);
        DB::disableQueryLog();

        return count(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select')));
    };

    // Reads must not grow with the number of scores; only the per-row writes do.
    expect($queriesFor(1))->toBe($queriesFor(3));
});
