<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CourseScripts\Application\Commands\GenerateVideoScriptHandler;
use Modules\CourseScripts\Application\Generation\GenerateVideoScriptCommand;
use Modules\CourseScripts\Domain\Exceptions\ScriptValidationException;
use Modules\CourseScripts\Infrastructure\Ai\GeneratePracticeArtifactAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptClosingAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptOutlineAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptSectionAgent;
use Modules\CourseScripts\Infrastructure\Ai\ReviewPracticeAgent;
use Modules\CourseScripts\Infrastructure\Ai\ReviewScriptAgent;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;
use Modules\CourseScripts\Tests\Support\CanonicalPracticePackFixture;
use Modules\CourseScripts\Tests\Support\CanonicalScriptFixture;
use Modules\CourseScripts\Tests\Support\FakeResearch;
use Modules\CourseScripts\Tests\Support\RecordingAiClient;

uses(RefreshDatabase::class);

/**
 * The per-video pipeline end to end (plan §3.5): real writer adapter, real
 * gates, real persistence — only the provider is replaced by recorded payloads.
 */
beforeEach(function (): void {
    $this->research = FakeResearch::install();
    $this->course = CourseEloquentModel::factory()->withBible()->withVideos(3)->create(['title' => 'Claude para usuarios']);
    $this->video = CourseVideoEloquentModel::query()->where('course_id', $this->course->id)->where('number', 2)->firstOrFail();
});

/**
 * @return array<class-string, mixed>
 */
function scriptPayloads(bool $withPractice = true, bool $withPrompts = true, array $overrides = []): array
{
    $mandatory = ['Contenido obligatorio 2'];

    return array_merge([
        GenerateScriptOutlineAgent::class => CanonicalScriptFixture::outlinePayload($mandatory, $withPractice),
        GenerateScriptSectionAgent::class => array_map(
            static fn (string $number): array => CanonicalScriptFixture::sectionPayload($number, $withPrompts, $withPractice),
            ['1', '2', '3', '4', '5', '3'],
        ),
        GenerateScriptClosingAgent::class => CanonicalScriptFixture::closingPayload($withPractice),
        GeneratePracticeArtifactAgent::class => [
            CanonicalScriptFixture::artifactPayload(CanonicalPracticePackFixture::FILE_A),
            CanonicalScriptFixture::artifactPayload(CanonicalPracticePackFixture::FILE_B),
        ],
    ], $overrides);
}

function generateVideo(object $test, bool $withReview = false): array
{
    $usage = null;
    $result = app(GenerateVideoScriptHandler::class)->handle(new GenerateVideoScriptCommand(
        courseId: $test->course->id,
        videoId: $test->video->id,
        writerProvider: 'openai',
        withReview: $withReview,
        reviewerProvider: $withReview ? 'anthropic' : null,
    ), $usage);

    return [$result, CourseScriptVersionEloquentModel::query()->with('practice', 'sources')->findOrFail($result->scriptVersionId)];
}

it('writes and stores a complete script with its practice pack', function (): void {
    $client = RecordingAiClient::install(scriptPayloads());

    [$result, $version] = generateVideo($this);

    expect($version->is_accepted)->toBeTrue()
        ->and($version->version)->toBe(1)
        ->and($version->technical_header['duration_minutes'])->toBe(9)
        ->and($version->technical_header['position_label'])->toBe('2 de 3 del bloque')
        ->and($version->sections)->toHaveCount(6)
        ->and(collect($version->sections)->firstWhere('number', '4.1')['segments'][0]['type'])->toBe('on_screen_table')
        ->and($version->uses_tool)->toBeTrue()
        ->and($version->summary_points)->not->toBeEmpty()
        ->and($version->next_video['number'])->toBe(3)
        ->and($version->verification_checklist)->toHaveCount(3)
        ->and($version->is_grounded)->toBeTrue()
        ->and($version->sources)->not->toBeEmpty()
        ->and($version->prompts_sheet_reason)->toBeNull()
        ->and($version->practice_warranted)->toBeTrue()
        ->and($version->reviewed)->toBeFalse();

    expect($version->practice->document_name)->toBe('Practica_Google_Workspace_Guion_02')
        ->and($version->practice->header_title)->toContain('DOCUMENTO DE PRÁCTICA · VÍDEO 02')
        ->and($version->practice->artifacts)->toHaveCount(2)
        ->and($version->practice->artifacts[0]['content_blocks'])->not->toBeEmpty()
        ->and(collect($version->practice->usage)->pluck('demo_label')->unique()->all())->toBe(['DEMO 2']);

    // 1 outline + 5 sections + 1 closing + 2 artifacts; no reviewer calls.
    expect($result->usage->aiWrite)->toBe(9)
        ->and($result->usage->aiReview)->toBe(0)
        ->and($result->usage->research)->toBeGreaterThan(0)
        ->and(collect($client->calls)->pluck('agent')->contains(ReviewScriptAgent::class))->toBeFalse();

    // Organisations introduced by the practice pack join the bible (FR-13k).
    expect(collect($this->course->fresh()->bible['organisations'])->pluck('name'))->toContain('Transportes Meridional S.L.');
});

it('writes no prompts and no prompts sheet for a course without a taught tool', function (): void {
    $this->course->update(['bible' => [...$this->course->bible, 'taught_tool' => null]]);
    $outline = CanonicalScriptFixture::outlinePayload(['Contenido obligatorio 2'], withPractice: false);
    $outline['uses_tool'] = true;

    RecordingAiClient::install(scriptPayloads(withPractice: false, withPrompts: false, overrides: [GenerateScriptOutlineAgent::class => $outline]));

    [, $version] = generateVideo($this);

    expect($version->uses_tool)->toBeFalse()
        ->and($version->prompts_sheet_reason)->toBe('no_taught_tool')
        ->and($version->practice)->toBeNull()
        ->and($version->practice_decision_reason)->toContain('conceptual');
});

it('retries an outline whose minutes do not add up', function (): void {
    $bad = CanonicalScriptFixture::outlinePayload(['Contenido obligatorio 2'], durationMinutes: 14);
    $client = RecordingAiClient::install(scriptPayloads(overrides: [
        GenerateScriptOutlineAgent::class => [$bad, CanonicalScriptFixture::outlinePayload(['Contenido obligatorio 2'])],
    ]));

    generateVideo($this);

    $outlineCalls = collect($client->calls)->where('agent', GenerateScriptOutlineAgent::class)->values();

    expect($outlineCalls)->toHaveCount(2)
        ->and($outlineCalls[1]['prompt'])->toContain('CORRECTIONS TO FIX')
        ->and($outlineCalls[1]['prompt'])->toContain('add up to 14');
});

it('fails the video and stores nothing when the outline never passes', function (): void {
    $bad = CanonicalScriptFixture::outlinePayload(['Otro contenido'], durationMinutes: 14);
    RecordingAiClient::install(scriptPayloads(overrides: [GenerateScriptOutlineAgent::class => $bad]));

    expect(fn () => generateVideo($this))->toThrow(ScriptValidationException::class)
        ->and(CourseScriptVersionEloquentModel::query()->count())->toBe(0);
});

it('rejects a practice file with a wrong total', function (): void {
    $broken = CanonicalScriptFixture::artifactPayload(CanonicalPracticePackFixture::FILE_A);

    foreach ($broken['content_blocks'] as $index => $block) {
        if ($block['total_row']) {
            $broken['content_blocks'][$index]['table_rows'][count($block['table_rows']) - 1][3] = '99.999 €';
        }
    }

    RecordingAiClient::install(scriptPayloads(overrides: [
        GeneratePracticeArtifactAgent::class => [$broken, CanonicalScriptFixture::artifactPayload(CanonicalPracticePackFixture::FILE_B), $broken],
    ]));

    expect(fn () => generateVideo($this))->toThrow(ScriptValidationException::class);
});

it('runs the second review only when asked, rewriting the targeted section', function (): void {
    $client = RecordingAiClient::install(scriptPayloads(overrides: [
        ReviewScriptAgent::class => [
            ['coverage' => 5, 'duration' => 8, 'format_fidelity' => 8, 'continuity' => 8, 'errors_to_avoid' => 8, 'integrity' => 8, 'objections' => [['target' => 'section 3', 'text' => 'La DEMO 1 no muestra el resultado esperado con detalle.']]],
            ['coverage' => 9, 'duration' => 9, 'format_fidelity' => 9, 'continuity' => 9, 'errors_to_avoid' => 9, 'integrity' => 9, 'objections' => []],
        ],
        ReviewPracticeAgent::class => ['realism' => 9, 'designed_contrasts' => 9, 'figures' => 10, 'integrity' => 9, 'objections' => []],
    ]));

    [$result, $version] = generateVideo($this, withReview: true);

    $sectionCalls = collect($client->calls)->where('agent', GenerateScriptSectionAgent::class)->values();
    $reviewCalls = collect($client->calls)->whereIn('agent', [ReviewScriptAgent::class, ReviewPracticeAgent::class]);

    expect($version->reviewed)->toBeTrue()
        ->and($version->reviewer_provider)->toBe('anthropic')
        ->and($version->passed_review)->toBeTrue()
        ->and($version->review_iterations)->toBe(2)
        ->and($version->review_scores['coverage'])->toBe(9)
        ->and($version->practice->review_scores['figures'])->toBe(10)
        ->and($reviewCalls)->toHaveCount(4)
        ->and($reviewCalls->pluck('provider')->unique()->all())->toBe(['anthropic'])
        ->and($sectionCalls)->toHaveCount(6)
        ->and($sectionCalls[5]['prompt'])->toContain('Write the content of section 3')
        ->and($result->usage->aiReview)->toBe(4)
        ->and($result->usage->aiWrite)->toBe(10);
});

it('delivers the best draft marked not passed when reviews never pass', function (): void {
    config()->set('course-scripts.runs.max_review_iterations', 1);

    RecordingAiClient::install(scriptPayloads(overrides: [
        ReviewScriptAgent::class => ['coverage' => 4, 'duration' => 5, 'format_fidelity' => 5, 'continuity' => 5, 'errors_to_avoid' => 5, 'integrity' => 5, 'objections' => [['target' => 'closing', 'text' => 'Resumen genérico.']]],
        ReviewPracticeAgent::class => ['realism' => 9, 'designed_contrasts' => 9, 'figures' => 9, 'integrity' => 9, 'objections' => []],
    ]));

    [, $version] = generateVideo($this, withReview: true);

    expect($version->reviewed)->toBeTrue()
        ->and($version->passed_review)->toBeFalse()
        ->and($version->review_objections[0]['text'])->toBe('Resumen genérico.');
});

it('keeps previous versions and accepts the newest on a new generation', function (): void {
    RecordingAiClient::install(scriptPayloads());
    [, $first] = generateVideo($this);

    RecordingAiClient::install(scriptPayloads());
    [, $second] = generateVideo($this);

    expect($second->version)->toBe(2)
        ->and($second->is_accepted)->toBeTrue()
        ->and($first->fresh()->is_accepted)->toBeFalse();
});
