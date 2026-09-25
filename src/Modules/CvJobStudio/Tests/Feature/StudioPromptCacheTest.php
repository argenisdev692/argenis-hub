<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Infrastructure\Ai\AiCallExecutor;
use Modules\CvJobStudio\Infrastructure\Ai\ExtractRequirementsAgent;
use Modules\CvJobStudio\Infrastructure\Ai\JudgeCvAgent;
use Modules\CvJobStudio\Infrastructure\Ai\LaravelAiMatchNarrator;
use Modules\CvJobStudio\Infrastructure\Ai\LaravelAiRequirementExtractor;
use Modules\CvJobStudio\Infrastructure\Ai\NarrateMatchAgent;
use Modules\CvJobStudio\Tests\Support\RecordingAiClient;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;
use Shared\Infrastructure\AI\PromptCache\PromptLayer;

/*
| CHG-20 marks `requirement_extraction` and `match_narrative` cache-flagged, and
| both agents implement `UsesPromptCache` — but neither adapter passed a
| `CacheablePrompt`, so the agents found an empty scope and emitted no cache
| fields at all. These tests pin that the two now cache, and that Anthropic
| receives only the tail (the layers travel as cached system blocks, so
| repeating them in the user message would pay for them twice).
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function cacheOwner(): User
{
    $owner = User::factory()->create();
    $owner->assignRole('SUPER_ADMIN');

    return $owner;
}

it('caches the extraction rubric and sends the posting text as the tail (CHG-20)', function (): void {
    $owner = cacheOwner();
    $ai = RecordingAiClient::install([
        ExtractRequirementsAgent::class => ['requirements' => [], 'responsibilities' => []],
    ]);

    app(LaravelAiRequirementExtractor::class)->extract('We need Laravel and Vue.js.', $owner->id);

    $scope = $ai->lastScope;

    expect($scope)->toBeInstanceOf(CacheablePrompt::class)
        ->and($scope->layers)->toHaveCount(1)
        ->and($scope->layers[0]->longLived)->toBeTrue()
        ->and($scope->layers[0]->text)->toContain('required')
        ->and($scope->layers[0]->text)->toContain('hard')
        ->and($scope->tail)->toContain('We need Laravel and Vue.js.')
        ->and($scope->cacheKey)->toStartWith('cvjs:requirement_extraction:');
});

it('caches the scoring contract for the match narrative (CHG-20)', function (): void {
    $owner = cacheOwner();
    $ai = RecordingAiClient::install([
        NarrateMatchAgent::class => ['why' => 'Strong Laravel depth.', 'gaps' => 'No Kubernetes evidence.'],
    ]);

    $narrative = app(LaravelAiMatchNarrator::class)->narrate(
        ['total' => 78.0, 'band' => 'good', 'matches' => [['requirement' => 'Laravel', 'credit' => 0.9]]],
        $owner->id,
    );

    $scope = $ai->lastScope;

    expect($scope)->toBeInstanceOf(CacheablePrompt::class)
        ->and($scope->layers[0]->text)->toContain('Scoring contract')
        ->and($scope->layers[0]->text)->toContain('strong')
        ->and($scope->tail)->toContain('Laravel')
        ->and($narrative['why'])->toBe('Strong Laravel depth.');
});

it('sends only the tail to Anthropic because the layers travel as cached system blocks', function (): void {
    $owner = cacheOwner();
    $ai = RecordingAiClient::install();

    config(['ai.purposes.requirement_extraction.provider' => 'anthropic']);

    $cached = new CacheablePrompt(
        layers: [PromptLayer::long('stable rubric')],
        tail: 'the untrusted posting text',
        cacheKey: 'cvjs:test:hash',
    );

    (void) app(AiCallExecutor::class)->call(
        AiPurpose::RequirementExtraction,
        ExtractRequirementsAgent::class,
        $cached->asSingleMessage(),
        $owner->id,
        null,
        $cached,
    );

    expect($ai->lastPrompt)->toBe('the untrusted posting text');
});

it('sends the whole prompt to a provider that caches by prefix', function (): void {
    $owner = cacheOwner();
    $ai = RecordingAiClient::install();

    config(['ai.purposes.requirement_extraction.provider' => 'openai']);

    $cached = new CacheablePrompt(
        layers: [PromptLayer::long('stable rubric')],
        tail: 'the untrusted posting text',
        cacheKey: 'cvjs:test:hash',
    );

    (void) app(AiCallExecutor::class)->call(
        AiPurpose::RequirementExtraction,
        ExtractRequirementsAgent::class,
        $cached->asSingleMessage(),
        $owner->id,
        null,
        $cached,
    );

    expect($ai->lastPrompt)->toContain('stable rubric')
        ->and($ai->lastPrompt)->toContain('the untrusted posting text');
});

it('ignores a cacheable prompt for a purpose that opts out of caching (CHG-20)', function (): void {
    $owner = cacheOwner();
    $ai = RecordingAiClient::install();

    // `cv_judge` is a one-off audit: an Anthropic write premium would never be
    // recouped, so the purpose opts out even when handed a cacheable prompt.
    config(['ai.purposes.cv_judge.prompt_cache' => false]);

    $cached = new CacheablePrompt(
        layers: [PromptLayer::long('stable rubric')],
        tail: 'the cv text',
        cacheKey: 'cvjs:cv_judge:hash',
    );

    (void) app(AiCallExecutor::class)->call(
        AiPurpose::CvJudge,
        JudgeCvAgent::class,
        $cached->asSingleMessage(),
        $owner->id,
        null,
        $cached,
    );

    expect($ai->lastScope)->toBeNull()
        ->and($ai->lastPrompt)->toContain('stable rubric')
        ->and($ai->lastPrompt)->toContain('the cv text');
});

it('bounds the untrusted tail with the purpose input ceiling (LLM10)', function (): void {
    $owner = cacheOwner();
    $ai = RecordingAiClient::install();

    config([
        'ai.purposes.requirement_extraction.provider' => 'anthropic',
        'ai.purposes.requirement_extraction.max_input_chars' => 20,
    ]);

    $cached = new CacheablePrompt(
        layers: [PromptLayer::long('stable rubric')],
        tail: str_repeat('x', 500),
        cacheKey: 'cvjs:test:hash',
    );

    (void) app(AiCallExecutor::class)->call(
        AiPurpose::RequirementExtraction,
        ExtractRequirementsAgent::class,
        $cached->asSingleMessage(),
        $owner->id,
        null,
        $cached,
    );

    expect($ai->lastPrompt)->toHaveLength(20);
});

it('clears the prompt scope after the call so no prompt leaks into the next one', function (): void {
    $owner = cacheOwner();
    RecordingAiClient::install();

    $cached = new CacheablePrompt(
        layers: [PromptLayer::long('stable rubric')],
        tail: 'tail',
        cacheKey: 'cvjs:test:hash',
    );

    (void) app(AiCallExecutor::class)->call(
        AiPurpose::RequirementExtraction,
        ExtractRequirementsAgent::class,
        $cached->asSingleMessage(),
        $owner->id,
        null,
        $cached,
    );

    expect(app(PromptCacheScope::class)->current())->toBeNull();
});

it('keeps the narrative agent on the stored breakdown only (NFR-2)', function (): void {
    $owner = cacheOwner();
    $ai = RecordingAiClient::install([
        NarrateMatchAgent::class => ['why' => 'Matches the core stack.', 'gaps' => 'No cloud evidence.'],
    ]);

    (void) app(AiCallExecutor::class)->call(
        AiPurpose::MatchNarrative,
        NarrateMatchAgent::class,
        '{"total":78}',
        $owner->id,
    );

    // Without a cacheable prompt the scope stays empty, so no CV content can
    // leak into a narrative that is allowed to carry no number.
    expect($ai->lastScope)->toBeNull();
});
