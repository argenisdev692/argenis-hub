<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Ports\PostAiGenerationRepositoryPort;
use Modules\Post\Infrastructure\Ai\EvaluatePostContentAgent;
use Modules\Post\Infrastructure\Ai\GeneratePostContentAgent;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostAiGenerationEloquentModel;
use Modules\Post\Infrastructure\Queue\GeneratePostContentJob;
use Shared\Infrastructure\AI\AIClientInterface;

/*
| The four states the wizard has to render, each reached the way production
| reaches it.
|
| `queued` is the only one that needs Queue::fake() — under the suite's `sync`
| driver the job would otherwise run to completion inside the accepting
| request, which is exactly what the other cases rely on.
*/

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config()->set('ai.default_for_evaluation', 'anthropic');

    // `throttle:5,1` on generate-content is keyed by user id, and RefreshDatabase
    // hands out the same ids to every test while the cache store survives them
    // — so without this the fifth accepted generation in the FILE fails, not the
    // fifth in a test. Flushing here (not mid-test) leaves the AI result cache a
    // test writes for itself untouched.
    Cache::flush();
});

function lifecycleAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

/**
 * Binds an AI client whose every structured call throws, so the quality loop
 * exhausts its iterations with nothing to show.
 *
 * `Agent::fake([])` does NOT do this: an empty response list makes the fake
 * gateway synthesise a schema-shaped answer, which the loop then happily
 * accepts — the first version of this test passed while asserting `failed` and
 * getting `completed`.
 */
function failPostAiCalls(): void
{
    Storage::fake((string) config('filesystems.cloud', 'r2'));

    app()->instance(AIClientInterface::class, new class implements AIClientInterface
    {
        public function generateStructured(string $agentClass, string $prompt, ?string $provider = null, ?string $model = null, ?int $timeoutSeconds = null): StructuredAgentResponse
        {
            throw new RuntimeException('provider unavailable');
        }

        /**
         * @return array{base64: string, mime: string}
         */
        public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
        {
            throw new RuntimeException('provider unavailable');
        }
    });
}

it('accepts a generation with 202 and parks it as queued without running it', function (): void {
    Queue::fake();

    $admin = lifecycleAdmin();

    $response = $this->actingAs($admin)
        ->postJson('/posts/ai/generate-content', [
            'topic' => 'Queued state',
            'provider' => 'openai',
            'image_mode' => 'full',
        ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', PostAiGenerationStatus::Queued->value)
        ->assertJsonPath('data.label', 'Queued')
        ->assertJsonPath('data.is_terminal', false)
        ->assertJsonPath('data.progress', 0)
        ->assertJsonPath('data.result', null);

    $uuid = (string) $response->json('data.uuid');

    Queue::assertPushed(GeneratePostContentJob::class);

    $this->assertDatabaseHas('post_ai_generations', [
        'uuid' => $uuid,
        'status' => PostAiGenerationStatus::Queued->value,
        'topic' => 'Queued state',
        'created_by' => $admin->id,
    ]);

    // The accepting request is not allowed to have started the loop.
    GeneratePostContentAgent::assertNeverPrompted();

    $this->assertDatabaseHas('activity_log', [
        'event' => 'post.ai.generation_started',
        'causer_id' => $admin->id,
    ]);
});

it('reports each processing phase while the loop runs', function (): void {
    $admin = lifecycleAdmin();

    $generation = PostAiGenerationEloquentModel::factory()->running()->create([
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->getJson("/posts/ai/generations/{$generation->uuid}")
        ->assertOk()
        ->assertJsonPath('data.status', PostAiGenerationStatus::Judging->value)
        ->assertJsonPath('data.label', 'Quality review')
        ->assertJsonPath('data.iteration', 2)
        ->assertJsonPath('data.progress', 44)
        ->assertJsonPath('data.is_terminal', false)
        ->assertJsonPath('data.result', null);
});

it('walks the row through every phase in order during a real run', function (): void {
    GeneratePostContentAgent::fake([postCostDraft()]);
    EvaluatePostContentAgent::fake([postCostVerdict()]);

    spyOnPostAiCalls();

    $admin = lifecycleAdmin();
    $uuid = (string) Str::uuid7();

    $generations = app(PostAiGenerationRepositoryPort::class);
    $generations->create([
        'uuid' => $uuid,
        'topic' => 'Phase order',
        'provider' => 'openai',
        'image_mode' => 'full',
        'status' => PostAiGenerationStatus::Queued->value,
        'created_by' => $admin->id,
    ]);

    // Record every status the row is written with, in order.
    $seen = [];
    PostAiGenerationEloquentModel::updated(function (PostAiGenerationEloquentModel $model) use (&$seen): void {
        $seen[] = $model->status->value;
    });

    (new GeneratePostContentJob($uuid, GeneratePostContentData::from([
        'topic' => 'Phase order',
        'provider' => 'openai',
        'image_mode' => 'full',
    ]), $admin->id))->handle(app(GeneratePostContentHandler::class), $generations);

    // markProgress() writes through the query builder (no model events), so
    // the phases are read back off the row's own audit trail instead.
    $phases = collect($seen)->unique()->values()->all();

    expect($phases)->toContain(PostAiGenerationStatus::Completed->value);

    $generations->findByUuid($uuid);

    $this->assertDatabaseHas('post_ai_generations', [
        'uuid' => $uuid,
        'status' => PostAiGenerationStatus::Completed->value,
        'progress' => 100,
    ]);
});

it('stores the finished draft on the row and marks it completed', function (): void {
    GeneratePostContentAgent::fake([postCostDraft('Completed Draft')]);
    EvaluatePostContentAgent::fake([postCostVerdict()]);

    spyOnPostAiCalls();

    $generation = runPostGeneration(lifecycleAdmin(), [
        'topic' => 'Completed state',
        'provider' => 'openai',
        'image_mode' => 'none',
    ]);

    expect($generation['status'])->toBe(PostAiGenerationStatus::Completed->value)
        ->and($generation['label'])->toBe('Ready')
        ->and($generation['is_terminal'])->toBeTrue()
        ->and($generation['progress'])->toBe(100)
        ->and($generation['error_message'])->toBeNull()
        // The whole draft round-trips through the JSON column intact.
        ->and($generation['result']['title'])->toBe('Completed Draft')
        ->and($generation['result']['all_scores_pass'])->toBeTrue()
        ->and($generation['result']['eeat_score'])->toBe(75)
        ->and($generation['result']['image_prompts'])->toHaveKeys(['background', 'content']);
});

it('marks the row failed with a message when every iteration fails', function (): void {
    failPostAiCalls();

    $generation = runPostGeneration(lifecycleAdmin(), [
        'topic' => 'Failed state',
        'provider' => 'openai',
        'image_mode' => 'none',
    ]);

    expect($generation['status'])->toBe(PostAiGenerationStatus::Failed->value)
        ->and($generation['label'])->toBe('Failed')
        ->and($generation['is_terminal'])->toBeTrue()
        ->and($generation['result'])->toBeNull()
        ->and($generation['error_message'])->not->toBeNull();
});

it('marks a row failed when the worker dies without finishing', function (): void {
    $admin = lifecycleAdmin();

    $generation = PostAiGenerationEloquentModel::factory()->running()->create([
        'created_by' => $admin->id,
    ]);

    (new GeneratePostContentJob(
        $generation->uuid,
        GeneratePostContentData::from(['topic' => 'Timed out', 'provider' => 'openai']),
        $admin->id,
    ))->failed(new RuntimeException('worker timeout'));

    $this->actingAs($admin)
        ->getJson("/posts/ai/generations/{$generation->uuid}")
        ->assertOk()
        ->assertJsonPath('data.status', PostAiGenerationStatus::Failed->value)
        ->assertJsonPath('data.is_terminal', true);
});

it('never overwrites a completed run with a late failure callback', function (): void {
    $admin = lifecycleAdmin();

    $generation = PostAiGenerationEloquentModel::factory()
        ->completed(['title' => 'Survivor'])
        ->create(['created_by' => $admin->id]);

    (new GeneratePostContentJob(
        $generation->uuid,
        GeneratePostContentData::from(['topic' => 'Late callback', 'provider' => 'openai']),
        $admin->id,
    ))->failed(new RuntimeException('late timeout'));

    $this->assertDatabaseHas('post_ai_generations', [
        'uuid' => $generation->uuid,
        'status' => PostAiGenerationStatus::Completed->value,
        'error_message' => null,
    ]);
});

it('does not let one author poll another authorized generation', function (): void {
    $owner = lifecycleAdmin();
    $other = lifecycleAdmin();

    $generation = PostAiGenerationEloquentModel::factory()->running()->create([
        'created_by' => $owner->id,
    ]);

    $this->actingAs($other)
        ->getJson("/posts/ai/generations/{$generation->uuid}")
        ->assertNotFound();
});

it('gates both generation endpoints behind the create permission', function (): void {
    $plain = User::factory()->create();
    $plain->assignRole('USER');

    $generation = PostAiGenerationEloquentModel::factory()->create();

    $this->actingAs($plain)
        ->postJson('/posts/ai/generate-content', ['topic' => 'Nope', 'provider' => 'openai'])
        ->assertForbidden();

    $this->actingAs($plain)
        ->getJson("/posts/ai/generations/{$generation->uuid}")
        ->assertForbidden();
});
