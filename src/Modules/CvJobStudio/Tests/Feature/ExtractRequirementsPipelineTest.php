<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Application\Commands\ExtractRequirementsHandler;
use Modules\CvJobStudio\Domain\Enums\GateCode;
use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;
use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Domain\Ports\PostingTextFetcherPort;
use Modules\CvJobStudio\Domain\Ports\RequirementExtractorPort;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Domain\Services\GateEvaluator;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Infrastructure\Ai\ExtractRequirementsAgent;
use Modules\CvJobStudio\Infrastructure\Ai\LaravelAiRequirementExtractor;
use Modules\CvJobStudio\Infrastructure\Fetching\PostingFetchLadder;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioGateResultEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioInsightReportEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingTextEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRequirementEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Queue\ExtractPostingsJob;
use Modules\CvJobStudio\Tests\Support\RecordingAiClient;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function extractPipeOwner(): User
{
    $owner = User::factory()->create();
    $owner->assignRole('SUPER_ADMIN');

    return $owner;
}

function extractPipeProfile(User $owner): StudioProfileEloquentModel
{
    return StudioProfileEloquentModel::query()->create([
        'user_id' => $owner->id,
        'name' => 'Fullstack',
        'slug' => 'fullstack-'.Str::lower(Str::random(6)),
        'flow' => 'fullstack',
        'is_active' => true,
        'accepted_remote_scopes' => ['remote_eu'],
        'stack_must' => ['Laravel', 'Vue.js'],
        'stack_reject' => ['WordPress'],
        'rules' => config('cv-job-studio'),
        'rules_version' => 2,
    ]);
}

function extractPipePosting(User $owner, StudioProfileEloquentModel $profile, string $suffix): StudioPostingEloquentModel
{
    return StudioPostingEloquentModel::query()->create([
        'user_id' => $owner->id,
        'profile_id' => $profile->id,
        'canonical_url' => "https://boards.greenhouse.io/acme/jobs/{$suffix}",
        'url_hash' => hash('sha256', "https://boards.greenhouse.io/acme/jobs/{$suffix}"),
        'title' => 'Senior Fullstack Developer',
        'remote_scope' => 'remote_eu',
        'status' => 'new',
    ]);
}

function extractPipeRun(User $owner, StudioProfileEloquentModel $profile): StudioRunEloquentModel
{
    return StudioRunEloquentModel::query()->create([
        'user_id' => $owner->id,
        'profile_id' => $profile->id,
        'status' => 'queued',
    ]);
}

function extractPipeFetcher(string $text, stdClass $calls): PostingTextFetcherPort
{
    return new class($text, $calls) implements PostingTextFetcherPort
    {
        public function __construct(private string $text, private stdClass $calls) {}

        public function fetch(string $url): ?array
        {
            $this->calls->fetch++;

            return ['text' => $this->text, 'completeness' => 'full', 'cost_micros' => 0];
        }

        public function stepName(): string
        {
            return 'direct_http';
        }
    };
}

function extractPipeExtractor(stdClass $calls): RequirementExtractorPort
{
    return new class($calls) implements RequirementExtractorPort
    {
        public function __construct(private stdClass $calls) {}

        public function extract(string $postingText, int $userId): array
        {
            $this->calls->extract++;

            return [
                'requirements' => [
                    ['canonical_name' => 'Laravel', 'raw_text' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
                    ['canonical_name' => 'Bogus', 'raw_text' => 'x', 'tag' => 'weird', 'nature' => 'hard'],
                ],
                'responsibilities' => [],
                'provider' => 'fake',
                'model' => 'fake-1',
                'prompt_version' => 'v2',
            ];
        }
    };
}

function extractPipeHandler(RequirementExtractorPort $extractor): ExtractRequirementsHandler
{
    app()->instance(EmbeddingPort::class, new class implements EmbeddingPort
    {
        public function embed(array $texts): array
        {
            return ['vectors' => [array_fill(0, 3, 0.1)], 'model' => 'fake', 'dims' => 3];
        }
    });

    return app(ExtractRequirementsHandler::class, ['extractor' => $extractor]);
}

function extractPipeCalls(): stdClass
{
    $calls = new stdClass;
    $calls->fetch = 0;
    $calls->extract = 0;

    return $calls;
}

it('extracts requirements for freshly fetched texts and feeds the insight report', function (): void {
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $posting = extractPipePosting($owner, $profile, 'fresh-1');
    $run = extractPipeRun($owner, $profile);
    $calls = extractPipeCalls();

    (new ExtractPostingsJob($run->id, $owner->id))->handle(
        new PostingFetchLadder([extractPipeFetcher('We need Laravel and Vue.js experience.', $calls)]),
        app(PostingTextMinimiser::class),
        app(SpendGuardPort::class),
        extractPipeHandler(extractPipeExtractor($calls)),
        app(GateEvaluator::class),
    );

    // Invalid enum tags rejected on the way out; the text was fetched once
    // and extracted exactly once.
    expect(StudioPostingEloquentModel::query()->count())->toBe(1)
        ->and(StudioPostingTextEloquentModel::query()->count())->toBe(1)
        ->and($run->refresh()->extracted_count)->toBe(1)
        ->and(StudioRequirementEloquentModel::query()->where('posting_id', $posting->id)->count())->toBe(1)
        ->and(StudioRequirementEloquentModel::query()->where('posting_id', $posting->id)->value('canonical_name'))->toBe('Laravel')
        ->and(StudioRequirementEloquentModel::query()->where('posting_id', $posting->id)->value('extracted_by_provider'))->toBe('fake')
        ->and($calls->fetch)->toBe(1)
        ->and($calls->extract)->toBe(1)
        ->and($run->refresh()->extracted_count)->toBe(1)
        // The sync-dispatched report stage closes the run afterwards.
        ->and($run->refresh()->status)->toBe('finished');

    // The sync-dispatched report stage now sees the posting: the frequency
    // section that used to render "No postings read in this run" populates.
    expect(StudioInsightReportEloquentModel::query()->where('run_id', $run->id)->value('jds_analyzed'))->toBe(1);
});

it('backfills requirements for texts stored by an earlier run', function (): void {
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $posting = extractPipePosting($owner, $profile, 'backfill-1');
    $posting->texts()->create([
        'user_id' => $owner->id,
        'ladder_step' => 'direct_http',
        'completeness' => 'full',
        'text' => 'We need Laravel experience.',
        'char_count' => 27,
    ]);
    $run = extractPipeRun($owner, $profile);
    $calls = extractPipeCalls();

    (new ExtractPostingsJob($run->id, $owner->id))->handle(
        new PostingFetchLadder([extractPipeFetcher('UNUSED', $calls)]),
        app(PostingTextMinimiser::class),
        app(SpendGuardPort::class),
        extractPipeHandler(extractPipeExtractor($calls)),
        app(GateEvaluator::class),
    );

    // No fetch (the text already exists), yet the requirement lands.
    expect($calls->fetch)->toBe(0)
        ->and($calls->extract)->toBe(1)
        ->and(StudioRequirementEloquentModel::query()->where('posting_id', $posting->id)->value('canonical_name'))->toBe('Laravel');
});

it('normalizes loosely-tagged model output instead of dropping paid rows', function (): void {
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $posting = extractPipePosting($owner, $profile, 'loose-1');
    $posting->texts()->create([
        'user_id' => $owner->id,
        'ladder_step' => 'direct_http',
        'completeness' => 'full',
        'text' => 'We need Laravel experience.',
        'char_count' => 27,
    ]);

    $loose = new class implements RequirementExtractorPort
    {
        public function extract(string $postingText, int $userId): array
        {
            return [
                'requirements' => [
                    ['canonical_name' => 'Laravel', 'raw_text' => 'Laravel', 'tag' => 'Required', 'nature' => 'Hard Skill'],
                    ['canonical_name' => 'Vue.js', 'raw_text' => 'Vue', 'tag' => 'must-have', 'nature' => 'technical'],
                    ['canonical_name' => 'Coffee', 'raw_text' => 'x', 'tag' => 'whatever', 'nature' => 'hard'],
                ],
                'responsibilities' => [],
                'provider' => 'fake',
                'model' => 'fake-1',
                'prompt_version' => 'v2',
            ];
        }
    };

    $rows = extractPipeHandler($loose)->handle($posting->uuid, $owner->id);

    // Capitalization and common aliases normalize; the unknown tag drops.
    expect($rows)->toHaveCount(2)
        ->and(StudioRequirementEloquentModel::query()->where('posting_id', $posting->id)->pluck('canonical_name')->all())
        ->toBe(['Laravel', 'Vue.js']);
});

it('records a dropped requirement in the log, so an empty report is explicable', function (): void {
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $posting = extractPipePosting($owner, $profile, 'discard-1');
    $posting->texts()->create([
        'user_id' => $owner->id,
        'ladder_step' => 'direct_http',
        'completeness' => 'full',
        'text' => 'We need Laravel experience.',
        'char_count' => 27,
    ]);

    $drifting = new class implements RequirementExtractorPort
    {
        public function extract(string $postingText, int $userId): array
        {
            return [
                'requirements' => [
                    ['canonical_name' => 'Laravel', 'raw_text' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
                    // A provider drifting off the enum contract. It used to
                    // vanish with no trace, leaving an empty insight report.
                    ['canonical_name' => 'Kubernetes', 'raw_text' => 'k8s', 'tag' => 'weird', 'nature' => 'hard'],
                    ['canonical_name' => 'GraphQL', 'raw_text' => 'gql', 'tag' => 'required', 'nature' => 'essential'],
                ],
                'responsibilities' => [],
                'provider' => 'fake',
                'model' => 'fake-1',
                'prompt_version' => 'v2',
            ];
        }
    };

    $logs = [];

    Log::listen(function (MessageLogged $message) use (&$logs): void {
        $logs[] = $message;
    });

    $rows = extractPipeHandler($drifting)->handle($posting->uuid, $owner->id);

    expect($rows)->toHaveCount(1);

    $warning = collect($logs)->first(
        fn (MessageLogged $message): bool => $message->level === 'warning'
            && $message->message === 'cv-job-studio.requirements_discarded'
    );

    $encoded = json_encode($warning?->context ?? [], JSON_THROW_ON_ERROR);

    expect($warning)->not->toBeNull()
        ->and($warning->context['discarded_count'])->toBe(2)
        ->and($warning->context['kept'])->toBe(1)
        ->and($warning->context['posting_uuid'])->toBe($posting->uuid)
        // Enough to diagnose a drift, without re-recording posting text.
        ->and(array_column($warning->context['discarded'], 'canonical_name'))->toBe(['Kubernetes', 'GraphQL'])
        ->and($encoded)->not->toContain('k8s')
        ->and($encoded)->not->toContain('gql');
});

it('logs nothing when every requirement is usable', function (): void {
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $posting = extractPipePosting($owner, $profile, 'clean-1');
    $posting->texts()->create([
        'user_id' => $owner->id,
        'ladder_step' => 'direct_http',
        'completeness' => 'full',
        'text' => 'We need Laravel experience.',
        'char_count' => 27,
    ]);

    $clean = new class implements RequirementExtractorPort
    {
        public function extract(string $postingText, int $userId): array
        {
            return [
                'requirements' => [
                    ['canonical_name' => 'Laravel', 'raw_text' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
                ],
                'responsibilities' => [],
                'provider' => 'fake',
                'model' => 'fake-1',
                'prompt_version' => 'v2',
            ];
        }
    };

    $messages = [];

    Log::listen(function (MessageLogged $message) use (&$messages): void {
        $messages[] = $message->message;
    });

    $kept = extractPipeHandler($clean)->handle($posting->uuid, $owner->id);

    expect($kept)->toHaveCount(1)
        ->and($messages)->not->toContain('cv-job-studio.requirements_discarded');
});

it('stops the stage on a spent LLM budget without failing the run', function (): void {
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $posting = extractPipePosting($owner, $profile, 'budget-1');
    $run = extractPipeRun($owner, $profile);
    $calls = extractPipeCalls();

    $spent = new class implements RequirementExtractorPort
    {
        public function extract(string $postingText, int $userId): array
        {
            throw new BudgetExceededException('llm budget spent');
        }
    };

    (new ExtractPostingsJob($run->id, $owner->id))->handle(
        new PostingFetchLadder([extractPipeFetcher('We need Laravel experience.', $calls)]),
        app(PostingTextMinimiser::class),
        app(SpendGuardPort::class),
        extractPipeHandler($spent),
        app(GateEvaluator::class),
    );

    // The text was still fetched, but no requirement rows exist; the run
    // degrades through the pipeline instead of throwing.
    expect($run->refresh()->extracted_count)->toBe(1)
        ->and(StudioRequirementEloquentModel::query()->where('posting_id', $posting->id)->count())->toBe(0);
});

it('reads the agent payload through array access, not the response property table', function (): void {
    // `(array) $response` yields `structured`/`usage`/`meta`, never the
    // payload keys — every test above fakes the port, so only a real adapter
    // call proves the extraction path survives a real provider response.
    $owner = extractPipeOwner();

    $ai = RecordingAiClient::install([
        ExtractRequirementsAgent::class => [
            'requirements' => [
                ['canonical_name' => 'Laravel', 'raw_text' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
            ],
            'responsibilities' => [
                ['text' => 'Ship features', 'must_do' => true],
            ],
        ],
    ]);

    $result = app(LaravelAiRequirementExtractor::class)->extract('We need Laravel experience.', $owner->id);

    expect($result['requirements'])->toHaveCount(1)
        ->and($result['requirements'][0]['canonical_name'])->toBe('Laravel')
        ->and($result['responsibilities'])->toHaveCount(1)
        ->and($result['provider'])->toBeString();

    $ai->requireCall();
});

it('decides the skill-floor gate once the requirements exist, not before', function (): void {
    // G5 needs the extracted requirements, and extraction runs AFTER the gate
    // stage. Deciding it in the gate stage can only ever pass, so the verdict
    // recorded there is provisional and this stage must restate it.
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $profile->update(['skill_floors' => ['Laravel' => 4, 'React' => 2]]);
    $posting = extractPipePosting($owner, $profile, 'floor-1');
    $run = extractPipeRun($owner, $profile);
    $calls = extractPipeCalls();

    // What the gate stage would have stored: a provisional pass, no
    // requirements in hand to contradict it.
    StudioGateResultEloquentModel::query()->create([
        'user_id' => $owner->id,
        'posting_id' => $posting->id,
        'gate_code' => GateCode::SkillFloor->value,
        'passed' => true,
        'reason_code' => null,
    ]);

    $frontendOnly = new class implements RequirementExtractorPort
    {
        public function extract(string $postingText, int $userId): array
        {
            return [
                'requirements' => [
                    ['canonical_name' => 'React', 'raw_text' => 'React', 'tag' => 'required', 'nature' => 'hard'],
                    ['canonical_name' => 'Vue', 'raw_text' => 'Vue', 'tag' => 'required', 'nature' => 'hard'],
                ],
                'responsibilities' => [],
                'provider' => 'fake',
                'model' => 'fake-1',
                'prompt_version' => 'v2',
            ];
        }
    };

    (new ExtractPostingsJob($run->id, $owner->id))->handle(
        new PostingFetchLadder([extractPipeFetcher('React and Vue only.', $calls)]),
        app(PostingTextMinimiser::class),
        app(SpendGuardPort::class),
        extractPipeHandler($frontendOnly),
        app(GateEvaluator::class),
    );

    $verdict = StudioGateResultEloquentModel::query()
        ->where('posting_id', $posting->id)
        ->where('gate_code', GateCode::SkillFloor->value)
        ->sole();

    // React/Vue are required and neither reaches the Laravel anchor, so the
    // provisional pass is replaced rather than left standing.
    expect($verdict->passed)->toBeFalse()
        ->and($verdict->reason_code)->toBe('anchor_missing')
        // One posting, filtered out: the run's tally is restated, not left
        // reporting the pre-extraction pass.
        ->and($run->refresh()->gate_passed_count)->toBe(0);
});

it('keeps the skill-floor gate passing when a requirement names the anchor', function (): void {
    $owner = extractPipeOwner();
    $profile = extractPipeProfile($owner);
    $profile->update(['skill_floors' => ['Laravel' => 4, 'React' => 2]]);
    $posting = extractPipePosting($owner, $profile, 'floor-2');
    $run = extractPipeRun($owner, $profile);
    $calls = extractPipeCalls();

    $mixed = new class implements RequirementExtractorPort
    {
        public function extract(string $postingText, int $userId): array
        {
            return [
                'requirements' => [
                    ['canonical_name' => 'Laravel', 'raw_text' => 'Laravel', 'tag' => 'required', 'nature' => 'hard'],
                    ['canonical_name' => 'React', 'raw_text' => 'React', 'tag' => 'required', 'nature' => 'hard'],
                ],
                'responsibilities' => [],
                'provider' => 'fake',
                'model' => 'fake-1',
                'prompt_version' => 'v2',
            ];
        }
    };

    (new ExtractPostingsJob($run->id, $owner->id))->handle(
        new PostingFetchLadder([extractPipeFetcher('Laravel and React.', $calls)]),
        app(PostingTextMinimiser::class),
        app(SpendGuardPort::class),
        extractPipeHandler($mixed),
        app(GateEvaluator::class),
    );

    expect(StudioGateResultEloquentModel::query()
        ->where('posting_id', $posting->id)
        ->where('gate_code', GateCode::SkillFloor->value)
        ->sole()->passed)->toBeTrue()
        ->and($run->refresh()->gate_passed_count)->toBe(1);
});
