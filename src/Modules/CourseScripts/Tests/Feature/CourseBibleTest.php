<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CourseScripts\Application\Commands\PrepareCourseHandler;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseResearchFindingEloquentModel;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;
use Modules\CourseScripts\Tests\Support\FakeBibleProposer;
use Modules\CourseScripts\Tests\Support\FakeResearch;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->bible = FakeBibleProposer::install();
    $this->research = FakeResearch::install();
    $this->author = CourseScriptTestUsers::author();
    $this->course = CourseEloquentModel::factory()->withVideos(3)->create([
        'user_id' => $this->author->id,
        'course_notes' => 'La empresa ficticia es Tecnoform.',
    ]);
});

function validBible(array $overrides = []): array
{
    return array_merge([
        'organisations' => [
            ['key' => 'lumitec', 'name' => 'Lumitec Distribución S.L.', 'role' => 'Empresa', 'sector' => 'Material eléctrico', 'is_primary' => true],
            ['key' => 'heliantia', 'name' => 'Heliantia Group', 'role' => 'Cliente', 'sector' => 'Logística', 'is_primary' => false],
        ],
        'characters' => [['name' => 'Javi', 'role' => 'Comercial', 'organisation_key' => 'lumitec']],
        'audience' => 'Comerciales',
        'tone' => 'Directo',
        'taught_tool' => 'Excel',
        'forbidden_phrasings' => [],
    ], $overrides);
}

it('proposes a bible from title, index and notes when the course has none', function (): void {
    $usage = app(PrepareCourseHandler::class)->handle($this->course, 'anthropic');

    $course = $this->course->fresh();

    expect($course->bible['organisations'][0]['name'])->toBe('Tecnoform S.A.')
        ->and($course->bible_origin)->toBe(BibleOrigin::Proposed)
        ->and($course->bible_revision)->toBe(1)
        ->and($course->prepared_at)->not->toBeNull()
        ->and($usage->aiWrite)->toBe(1)
        ->and($this->bible->calls[0]['provider'])->toBe('anthropic')
        ->and($this->bible->calls[0]['context']->tableOfContents)->toBe(['1. Vídeo 1', '2. Vídeo 2', '3. Vídeo 3'])
        ->and($this->bible->calls[0]['context']->courseNotes)->toContain('Tecnoform');
});

it('never overwrites a bible the author already has', function (): void {
    $course = CourseEloquentModel::factory()->withBible()->withVideos(1)->create(['user_id' => $this->author->id]);

    $usage = app(PrepareCourseHandler::class)->handle($course, 'openai');

    expect($this->bible->calls)->toBe([])
        ->and($usage->aiWrite)->toBe(0)
        ->and($course->fresh()->bible_origin)->toBe(BibleOrigin::Author);
});

it('researches the subject once and reuses it', function (): void {
    $handler = app(PrepareCourseHandler::class);

    $first = $handler->handle($this->course, 'openai');
    $second = $handler->handle($this->course->fresh(), 'openai');

    expect($first->research)->toBeGreaterThan(0)
        ->and($second->research)->toBe(0)
        ->and($this->research->searches)->toHaveCount(1)
        ->and($this->research->searches[0]['time_range'])->toBe('year')
        ->and(CourseResearchFindingEloquentModel::query()->whereNull('course_video_id')->count())->toBe($first->research);
});

it('keeps preparing when research is down', function (): void {
    $this->research->outage = true;

    $usage = app(PrepareCourseHandler::class)->handle($this->course, 'openai');

    expect($this->course->fresh()->prepared_at)->not->toBeNull()
        ->and(CourseResearchFindingEloquentModel::query()->count())->toBe(0)
        ->and($usage->aiWrite)->toBe(1);
});

it('reports a failed bible proposal', function (): void {
    $this->bible->fail = true;

    expect(fn () => app(PrepareCourseHandler::class)->handle($this->course, 'openai'))
        ->toThrow(GenerationProviderException::class);
});

it('lets the author edit the bible and bumps the revision', function (): void {
    $this->actingAs($this->author)
        ->putJson(route('course-scripts.bible.update', $this->course->uuid), validBible())
        ->assertOk()
        ->assertJsonPath('data.bible_revision', 1)
        ->assertJsonPath('data.stale_script_count', 0)
        ->assertJsonPath('data.bible.organisations.1.name', 'Heliantia Group');

    $course = $this->course->fresh();

    expect($course->bible_origin)->toBe(BibleOrigin::Author)
        ->and($course->bible['taught_tool'])->toBe('Excel');
});

it('requires exactly one primary organisation', function (): void {
    $bible = validBible();
    $bible['organisations'][1]['is_primary'] = true;

    $this->actingAs($this->author)
        ->putJson(route('course-scripts.bible.update', $this->course->uuid), $bible)
        ->assertStatus(422)
        ->assertJsonValidationErrors('organisations');
});

it('queues preparation with an allowed provider only', function (): void {
    $this->actingAs($this->author)
        ->postJson(route('course-scripts.prepare', $this->course->uuid), ['writer_provider' => 'gpt-5-custom'])
        ->assertStatus(422);

    $this->actingAs($this->author)
        ->postJson(route('course-scripts.prepare', $this->course->uuid), ['writer_provider' => 'gemini'])
        ->assertStatus(202);

    expect($this->course->fresh()->bible)->not->toBeNull()
        ->and($this->bible->calls[0]['provider'])->toBe('gemini');
});
