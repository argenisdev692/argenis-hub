<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\CvJobStudio\Application\Commands\IngestPostingHandler;
use Modules\CvJobStudio\Application\DTOs\IngestPostingData;
use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;
use Modules\CvJobStudio\Domain\Ports\PostingTextFetcherPort;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Infrastructure\Ai\AiCallExecutor;
use Modules\CvJobStudio\Infrastructure\Ai\JudgeCvAgent;
use Modules\CvJobStudio\Infrastructure\Budgets\StudioBudgetLedger;
use Modules\CvJobStudio\Infrastructure\Fetching\OutboundUrlGuard;
use Modules\CvJobStudio\Infrastructure\Fetching\PostingFetchLadder;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioBudgetEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProviderCallEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Queue\ExtractPostingsJob;
use Modules\CvJobStudio\Infrastructure\Queue\GatePostingsJob;
use Shared\Infrastructure\AI\AIClientInterface;

/*
| Regression tests for the 2026-09-18 backend audit. Each test pins one
| defect that was live before the fix (SSRF bypasses, global url_hash,
| unprovisioned/uncharged budgets, stuck runs, unminimised fetched text,
| raw-model responses, one-sided User relations).
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function auditOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole('SUPER_ADMIN');

    return $user;
}

function auditProfile(User $owner): StudioProfileEloquentModel
{
    return StudioProfileEloquentModel::factory()->create(['user_id' => $owner->id]);
}

function auditIngest(User $owner, StudioProfileEloquentModel $profile, string $url = 'https://boards.greenhouse.io/acme/jobs/4242'): StudioPostingEloquentModel
{
    return app(IngestPostingHandler::class)->handle(IngestPostingData::from([
        'profile_uuid' => $profile->uuid,
        'title' => 'Senior Laravel Developer',
        'canonical_url' => $url,
        'employer_name' => 'Acme',
        'location_text' => 'Remote, EU',
        'source' => 'greenhouse',
        'discovery_channel' => 'employer_ats',
    ]), $owner->id);
}

// ── SSRF (OWASP A01:2025 / §15) ─────────────────────────────────────────

it('refuses IPv6-literal, IPv4-mapped, 0.0.0.0 and CGNAT targets', function (string $url): void {
    $guard = new OutboundUrlGuard(resolver: static fn (string $host): array => ['93.184.215.14']);

    expect($guard->allowed($url))->toBeFalse();
})->with([
    'ipv6 loopback' => 'http://[::1]/',
    'ipv4-mapped loopback' => 'http://[::ffff:127.0.0.1]/',
    'ipv6 link-local' => 'http://[fe80::1]/',
    'ipv6 unique-local' => 'http://[fc00::1]/',
    'unspecified' => 'http://0.0.0.0/',
    'cgnat' => 'http://100.64.0.1/',
    'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'non-http scheme' => 'file:///etc/passwd',
]);

it('refuses a public host that resolves to a private address (A or AAAA)', function (): void {
    $guard = new OutboundUrlGuard(resolver: static fn (string $host): array => ['93.184.215.14', '::1']);

    expect($guard->allowed('https://rebind.example/'))->toBeFalse();
});

it('re-validates every redirect hop before following it', function (): void {
    $guard = new OutboundUrlGuard(resolver: static fn (string $host): array => ['93.184.215.14']);
    $onRedirect = $guard->httpOptions()['allow_redirects']['on_redirect'];

    expect(fn () => $onRedirect(null, null, new Uri('http://169.254.169.254/latest')))
        ->toThrow(RuntimeException::class);

    $onRedirect(null, null, new Uri('https://jobs.example/next'));
    expect($guard->httpOptions()['allow_redirects']['max'])->toBe(3);
});

it('builds the container guard with the configured never-fetch hosts (FR-50, SC-16)', function (): void {
    $guard = app(OutboundUrlGuard::class);

    expect($guard->allowed('https://www.linkedin.com/jobs/view/1'))->toBeFalse()
        ->and($guard->allowed('https://es.indeed.com/viewjob?jk=1'))->toBeFalse();
});

// ── Ingest identity ─────────────────────────────────────────────────────

it('lets two users ingest the same posting URL', function (): void {
    [$first, $second] = [auditOwner(), auditOwner()];

    $a = auditIngest($first, auditProfile($first));
    $b = auditIngest($second, auditProfile($second));

    expect($a->id)->not->toBe($b->id)
        ->and($a->url_hash)->toBe($b->url_hash);
});

it('re-ingesting a soft-deleted URL keeps it deleted instead of crashing', function (): void {
    $owner = auditOwner();
    $profile = auditProfile($owner);

    $posting = auditIngest($owner, $profile);
    $posting->delete();

    $again = auditIngest($owner, $profile);

    expect($again->id)->toBe($posting->id)
        ->and($again->trashed())->toBeTrue()
        ->and(StudioPostingEloquentModel::withTrashed()->count())->toBe(1);
});

// ── Budgets (FR-33, SC-8, LLM10) ────────────────────────────────────────

it('provisions the period budget from config instead of refusing a new user', function (): void {
    $owner = auditOwner();

    (new StudioBudgetLedger)->ensure('llm', $owner->id);

    $row = StudioBudgetEloquentModel::query()->where('user_id', $owner->id)->where('category', 'llm')->sole();

    expect($row->limit_micros)->toBe(StudioBudgetLedger::limitMicros('llm'))
        ->and($row->limit_micros)->toBeGreaterThan(0);
});

it('charges every LLM call to the real user and stops once the budget is spent', function (): void {
    $owner = auditOwner();
    config(['cv-job-studio.budgets.llm.limit_eur' => 0.03, 'cv-job-studio.llm_call_estimate_eur' => 0.02]);

    $ai = Mockery::mock(AIClientInterface::class);
    $ai->shouldReceive('generateStructured')->andReturn(Mockery::mock(StructuredAgentResponse::class));
    app()->instance(AIClientInterface::class, $ai);

    $executor = app(AiCallExecutor::class);

    (void) $executor->call(AiPurpose::CvJudge, JudgeCvAgent::class, 'cv', $owner->id);
    (void) $executor->call(AiPurpose::CvJudge, JudgeCvAgent::class, 'cv', $owner->id);

    expect(StudioProviderCallEloquentModel::query()->where('user_id', $owner->id)->count())->toBe(2)
        ->and(fn () => $executor->call(AiPurpose::CvJudge, JudgeCvAgent::class, 'cv', $owner->id))
        ->toThrow(BudgetExceededException::class);
});

// ── Pipeline (A10:2025, NFR-8) ──────────────────────────────────────────

it('marks the run failed when a stage exhausts its tries', function (): void {
    $owner = auditOwner();
    $profile = auditProfile($owner);
    $run = StudioRunEloquentModel::query()->create([
        'user_id' => $owner->id,
        'profile_id' => $profile->id,
        'status' => 'harvesting',
    ]);

    (new GatePostingsJob($run->id, $owner->id))->failed(new RuntimeException('boom'));

    expect($run->refresh()->status)->toBe('failed')
        ->and($run->finished_at)->not->toBeNull();
});

it('minimises fetched posting text before storing it (NFR-8)', function (): void {
    $owner = auditOwner();
    $profile = auditProfile($owner);
    $posting = auditIngest($owner, $profile);
    $run = StudioRunEloquentModel::query()->create(['user_id' => $owner->id, 'profile_id' => $profile->id, 'status' => 'gated']);

    $fetcher = new class implements PostingTextFetcherPort
    {
        public function fetch(string $url): ?array
        {
            return ['text' => "We need Laravel.\nContact jane.recruiter@acme.test or +34 600 123 456.", 'completeness' => 'full', 'cost_micros' => 0];
        }

        public function stepName(): string
        {
            return 'direct_http';
        }
    };

    (new ExtractPostingsJob($run->id, $owner->id))->handle(
        new PostingFetchLadder([$fetcher]),
        app(PostingTextMinimiser::class),
        app(SpendGuardPort::class),
    );

    $stored = StudioPostingTextEloquentModel::query()->where('posting_id', $posting->id)->sole()->text;

    expect($stored)->toContain('We need Laravel.')
        ->and($stored)->not->toContain('jane.recruiter@acme.test')
        ->and($stored)->not->toContain('600 123 456');
});

// ── Response allowlists (OWASP §12, Response Shape Rule) ────────────────

it('serves versions, applications and relations through Data allowlists', function (): void {
    $owner = auditOwner();
    $posting = auditIngest($owner, auditProfile($owner));

    StudioCvVersionEloquentModel::query()->create([
        'user_id' => $owner->id, 'cv_id' => 1, 'posting_id' => $posting->id, 'purpose' => 'ats_rewrite', 'language' => 'en',
    ]);
    StudioApplicationEloquentModel::query()->create([
        'user_id' => $owner->id, 'posting_id' => $posting->id, 'status' => 'applied', 'applied_at' => now(),
    ]);
    StudioSkillRelationEloquentModel::query()->create([
        'user_id' => $owner->id, 'from_skill' => 'Laravel', 'to_skill' => 'PHP', 'kind' => 'implies', 'origin' => 'llm', 'status' => 'pending',
    ]);

    $this->actingAs($owner);

    $version = $this->getJson('/cv-studio/versions')->assertOk()->json('data.0');
    $application = $this->getJson('/cv-studio/applications')->assertOk()->json('data.0');
    $relation = $this->getJson('/cv-studio/relations')->assertOk()->json('data.0');

    expect(array_keys($version))->toBe(['uuid', 'purpose', 'language', 'created_at'])
        ->and($application)->not->toHaveKeys(['id', 'user_id', 'posting_id'])
        ->and($application['posting'])->toMatchArray(['uuid' => $posting->uuid, 'employer_name' => 'Acme'])
        ->and($application['posting'])->not->toHaveKey('id')
        ->and($relation)->toMatchArray(['from_skill' => 'Laravel', 'to_skill' => 'PHP', 'status' => 'pending'])
        ->and($relation)->not->toHaveKeys(['id', 'user_id']);
});

// ── Routes + relations ──────────────────────────────────────────────────

it('rejects a non-UUID requirement id on the dismiss route', function (): void {
    $owner = auditOwner();
    $posting = auditIngest($owner, auditProfile($owner));

    $this->actingAs($owner)
        ->post("/cv-studio/postings/{$posting->uuid}/requirements/not-a-uuid/dismiss")
        ->assertNotFound();
});

it('declares the User inverse of every studio model that belongs to a user', function (string $relation): void {
    expect(method_exists(User::class, $relation))->toBeTrue();
})->with(['studioGateResults', 'studioPostingTexts', 'studioRequirements', 'studioScores', 'studioSkillMatches']);
