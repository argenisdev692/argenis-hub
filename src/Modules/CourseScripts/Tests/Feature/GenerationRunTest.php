<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CourseScripts\Application\Commands\StartGenerationRunHandler;
use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptOutlineAgent;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;
use Modules\CourseScripts\Tests\Support\FakeResearch;
use Modules\CourseScripts\Tests\Support\FakeStorage;
use Modules\CourseScripts\Tests\Support\GenerationScenario;
use Modules\CourseScripts\Tests\Support\RecordingAiClient;
use Modules\CourseScripts\Tests\Support\RecordingDispatcher;

uses(RefreshDatabase::class);

/**
 * Runs over HTTP with the real batch/chain on the sync queue (US-9, US-12, US-13).
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = FakeStorage::install();
    FakeResearch::install();
    $this->author = CourseScriptTestUsers::author();
    $this->course = CourseEloquentModel::factory()->withBible()->withVideos(3)->create(['user_id' => $this->author->id]);
    $this->videos = $this->course->videos()->orderBy('number')->get();
});

function runRequest(object $test, array $overrides = []): array
{
    $scope = array_merge(['scope' => 'selection', 'video_uuids' => [$test->videos[0]->uuid, $test->videos[1]->uuid]], $overrides);
    $estimate = $test->actingAs($test->author)->postJson(route('course-scripts.runs.estimate', $test->course->uuid), $scope)->assertOk()->json('data');

    return [...$scope, 'writer_provider' => 'openai', 'confirmed_estimate' => [
        'ai_write_calls' => $estimate['ai_write_calls'],
        'ai_review_calls' => $estimate['ai_review_calls'],
        'research_calls' => $estimate['research_calls'],
    ]];
}

it('estimates review calls only when the second review is requested', function (): void {
    $url = route('course-scripts.runs.estimate', $this->course->uuid);
    $scope = ['scope' => 'course'];

    $plain = $this->actingAs($this->author)->postJson($url, $scope)->assertOk()->json('data');
    $reviewed = $this->actingAs($this->author)->postJson($url, [...$scope, 'with_review' => true])->assertOk()->json('data');

    expect($plain['ai_review_calls'])->toBe(0)
        ->and($plain['with_review'])->toBeFalse()
        ->and($reviewed['ai_review_calls'])->toBeGreaterThan(0)
        ->and($reviewed['ai_write_calls'])->toBeGreaterThanOrEqual($plain['ai_write_calls'])
        ->and($plain['fits'])->toBeTrue();
});

it('runs selected videos in course order and produces every deliverable', function (): void {
    $client = RecordingAiClient::install(GenerationScenario::payloads([1, 2]));

    $response = $this->actingAs($this->author)->postJson(route('course-scripts.runs.store', $this->course->uuid), runRequest($this, [
        'video_uuids' => [$this->videos[1]->uuid, $this->videos[0]->uuid],
    ]))->assertStatus(202);

    $run = CourseGenerationRunEloquentModel::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($run->status->value)->toBe('completed')
        ->and($run->with_review)->toBeFalse()
        ->and($run->videos_completed)->toBe(2)
        ->and($run->ai_write_calls_consumed)->toBe(18)
        ->and($run->ai_review_calls_consumed)->toBe(0)
        ->and($run->research_calls_consumed)->toBeGreaterThan(0)
        ->and($run->batch_id)->not->toBeNull();

    // Course order: video 1's outline is written before video 2's.
    $outlinePrompts = collect($client->calls)->where('agent', GenerateScriptOutlineAgent::class)->pluck('prompt')->values();
    expect($outlinePrompts[0])->toContain('VIDEO 1:')
        ->and($outlinePrompts[1])->toContain('VIDEO 2:');

    // Script, prompts sheet, practice document and two practice files, each md + pdf.
    $version = CourseScriptVersionEloquentModel::query()->where('course_video_id', $this->videos[0]->id)->firstOrFail();
    $deliverables = CourseDeliverableEloquentModel::query()->where('course_script_version_id', $version->id)->get();

    expect($deliverables)->toHaveCount(10)
        ->and($deliverables->pluck('document_type')->map->value->unique()->sort()->values()->all())->toBe(['practice', 'practice_file', 'prompts', 'script'])
        ->and($this->storage->get($deliverables->firstWhere(fn ($d) => $d->document_type->value === 'script' && $d->format->value === 'pdf')->path))->toStartWith('%PDF');

    $this->actingAs($this->author)->getJson(route('course-scripts.runs.show', $run->uuid))
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.progress', 100)
        ->assertJsonPath('data.outcomes.0.number', 1)
        ->assertJsonPath('data.outcomes.1.status', 'completed');

    expect($this->course->fresh()->status->value)->toBe('partially_generated')
        ->and($this->videos[2]->fresh()->script_status->value)->toBe('not_started');
});

it('keeps going when one video fails and retries only the failed one', function (): void {
    RecordingAiClient::install(GenerationScenario::payloads([1, 2], brokenOutlines: [1]));

    $response = $this->actingAs($this->author)->postJson(route('course-scripts.runs.store', $this->course->uuid), runRequest($this))->assertStatus(202);
    $run = CourseGenerationRunEloquentModel::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($run->status->value)->toBe('partially_failed')
        ->and($run->videos_failed)->toBe(1)
        ->and($run->videos_completed)->toBe(1)
        ->and($run->outcomes()->where('status', 'failed')->value('failure_reason'))->toBe('validation_failed:outline')
        ->and($this->videos[0]->fresh()->script_status->value)->toBe('failed');

    RecordingAiClient::install(GenerationScenario::payloads([1]));

    $retry = $this->actingAs($this->author)->postJson(route('course-scripts.runs.retry-failed', $run->uuid))->assertStatus(202);

    expect($retry->json('data.videos_total'))->toBe(1)
        ->and($retry->json('data.status'))->toBe('completed')
        ->and($retry->json('data.outcomes.0.number'))->toBe(1)
        ->and($this->videos[0]->fresh()->script_status->value)->toBe('generated');
});

it('stops a run that reaches the call ceiling and keeps finished videos', function (): void {
    config()->set('course-scripts.runs.max_ai_calls_per_run', 9);
    RecordingAiClient::install(GenerationScenario::payloads([1, 2]));

    $run = app(StartGenerationRunHandler::class)->start(
        course: $this->course,
        videoIds: [$this->videos[0]->id, $this->videos[1]->id],
        writerProvider: 'openai',
        withReview: false,
        scope: 'selection',
        blockId: null,
        userId: $this->author->id,
        estimate: ['ai_write_calls' => 0, 'ai_review_calls' => 0, 'research_calls' => 0],
        kind: GenerationRunKind::Generation,
    )->fresh();

    expect($run->status->value)->toBe('stopped_at_ceiling')
        ->and($run->stop_reason)->toBe('call_ceiling_reached')
        ->and($run->videos_completed)->toBe(1)
        ->and($run->outcomes()->where('status', 'skipped')->count())->toBe(1)
        ->and(CourseScriptVersionEloquentModel::query()->count())->toBe(1);
});

it('refuses a stale estimate and an estimate above the ceiling', function (): void {
    $payload = runRequest($this);
    $payload['confirmed_estimate']['ai_write_calls']++;

    $this->actingAs($this->author)->postJson(route('course-scripts.runs.store', $this->course->uuid), $payload)
        ->assertStatus(422)
        ->assertJsonPath('code', 'estimate_mismatch');

    config()->set('course-scripts.runs.max_ai_calls_per_run', 5);

    $this->actingAs($this->author)->postJson(route('course-scripts.runs.store', $this->course->uuid), runRequest($this))
        ->assertStatus(422)
        ->assertJsonPath('code', 'estimate_exceeds_ceiling');
});

it('allows one active run per course, reports the second review, and cancels', function (): void {
    $dispatcher = RecordingDispatcher::install();
    config()->set('ai.default_for_evaluation', 'openai');

    $first = $this->actingAs($this->author)->postJson(route('course-scripts.runs.store', $this->course->uuid), runRequest($this, ['with_review' => true]))
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.with_review', true)
        ->assertJsonPath('data.reviewer_provider', 'openai')
        ->assertJsonPath('data.reviewer_not_independent', true);

    $this->actingAs($this->author)->postJson(route('course-scripts.runs.store', $this->course->uuid), runRequest($this))
        ->assertStatus(409)
        ->assertJsonPath('code', 'run_in_progress');

    $this->actingAs($this->author)->postJson(route('course-scripts.runs.cancel', $first->json('data.uuid')))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.outcomes.0.status', 'skipped');

    expect($dispatcher->cancelled)->toBe(['batch-'.CourseGenerationRunEloquentModel::query()->value('id')]);
});

it('treats another user\'s run as not found', function (): void {
    RecordingDispatcher::install();
    $response = $this->actingAs($this->author)->postJson(route('course-scripts.runs.store', $this->course->uuid), runRequest($this))->assertStatus(202);

    $this->actingAs(CourseScriptTestUsers::author())->getJson(route('course-scripts.runs.show', $response->json('data.uuid')))->assertNotFound();
});
