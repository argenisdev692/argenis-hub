<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseSourceDocumentEloquentModel;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;
use Modules\CourseScripts\Tests\Support\FakeStorage;
use Modules\CourseScripts\Tests\Support\IndexFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = FakeStorage::install();
    $this->author = CourseScriptTestUsers::author();
    $this->course = CourseEloquentModel::factory()->withVideos(2)->create(['user_id' => $this->author->id]);
    $this->video = $this->course->videos()->orderBy('number')->firstOrFail();
});

function briefPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Vídeo editado',
        'topic' => 'Tema',
        'declared_duration_minutes' => 7,
        'objective' => 'Un objetivo claro.',
        'learning_areas' => ['Área'],
        'audience_objectives' => [],
        'mandatory_content' => ['Punto uno', ' ', 'Punto dos'],
        'errors_to_avoid' => [],
        'expected_result' => null,
        'notes' => 'Mis apuntes nuevos.',
    ], $overrides);
}

it('stores an edited brief and bumps its revision', function (): void {
    $this->actingAs($this->author)
        ->putJson(route('course-scripts.videos.update', [$this->course->uuid, $this->video->uuid]), briefPayload())
        ->assertOk()
        ->assertJsonPath('data.title', 'Vídeo editado')
        ->assertJsonPath('data.mandatory_content', ['Punto uno', 'Punto dos'])
        ->assertJsonPath('data.notes', 'Mis apuntes nuevos.')
        ->assertJsonPath('data.brief_revision', 2)
        ->assertJsonPath('data.needs_review', false);
});

it('does not bump the revision when nothing changed', function (): void {
    $url = route('course-scripts.videos.update', [$this->course->uuid, $this->video->uuid]);

    $this->actingAs($this->author)->putJson($url, briefPayload())->assertJsonPath('data.brief_revision', 2);
    $this->actingAs($this->author)->putJson($url, briefPayload())->assertJsonPath('data.brief_revision', 2);
});

it('validates the brief', function (): void {
    $this->actingAs($this->author)
        ->putJson(route('course-scripts.videos.update', [$this->course->uuid, $this->video->uuid]), briefPayload(['title' => '', 'declared_duration_minutes' => 0]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'declared_duration_minutes']);
});

it('edits the course notes', function (): void {
    $this->actingAs($this->author)
        ->putJson(route('course-scripts.course-notes.update', $this->course->uuid), ['course_notes' => '  Notas del curso  '])
        ->assertNoContent();

    expect($this->course->fresh()->course_notes)->toBe('Notas del curso');
});

it('attaches a content file to a video and detaches it', function (): void {
    $response = $this->actingAs($this->author)->postJson(route('course-scripts.contents.store', $this->course->uuid), [
        'file' => UploadedFile::fake()->createWithContent('apuntes.md', (string) file_get_contents(IndexFixtures::path('content-notes.md'))),
        'video_number' => 2,
    ])->assertCreated()->assertJsonPath('data.kind', 'content');

    $document = CourseSourceDocumentEloquentModel::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($response->json('data.video_uuid'))->toBe($this->course->videos()->where('number', 2)->value('uuid'))
        ->and($this->storage->exists($document->path))->toBeTrue();

    $this->actingAs($this->author)
        ->deleteJson(route('course-scripts.documents.destroy', [$this->course->uuid, $document->uuid]))
        ->assertNoContent();

    expect(CourseSourceDocumentEloquentModel::query()->count())->toBe(0)
        ->and($this->storage->exists($document->path))->toBeFalse();
});

it('refuses a content file beyond the configured limit', function (): void {
    config()->set('course-scripts.uploads.max_content_files', 1);
    $url = route('course-scripts.contents.store', $this->course->uuid);
    $file = static fn () => UploadedFile::fake()->createWithContent('a.md', "## Apuntes\n\nTexto de apuntes.\n");

    $this->actingAs($this->author)->postJson($url, ['file' => $file()])->assertCreated();
    $this->actingAs($this->author)->postJson($url, ['file' => $file()])
        ->assertStatus(422)
        ->assertJsonPath('code', 'document_limit_reached');
});

it('treats another user\'s course and video as not found', function (): void {
    $stranger = CourseScriptTestUsers::author();

    $this->actingAs($stranger)
        ->putJson(route('course-scripts.videos.update', [$this->course->uuid, $this->video->uuid]), briefPayload())
        ->assertNotFound();

    $this->actingAs($stranger)->getJson(route('course-scripts.show', $this->course->uuid))->assertNotFound();

    expect($this->video->fresh()->title)->toBe('Vídeo 1');
});

it('lists only the author\'s own courses with their counts', function (): void {
    CourseEloquentModel::factory()->withVideos(1)->create();

    $this->actingAs($this->author)->getJson(route('course-scripts.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $this->course->uuid)
        ->assertJsonPath('data.0.videos_count', 2)
        ->assertJsonPath('data.0.generated_videos_count', 0);
});

it('soft deletes a course', function (): void {
    $this->actingAs($this->author)->deleteJson(route('course-scripts.destroy', $this->course->uuid))->assertNoContent();

    expect(CourseEloquentModel::query()->count())->toBe(0)
        ->and(CourseEloquentModel::withTrashed()->count())->toBe(1);
});

it('lists a video\'s version history as metadata only', function (): void {
    $version = CourseScriptVersionEloquentModel::query()->create([
        'course_video_id' => $this->video->id,
        'version' => 1,
        'writer_provider' => 'openai',
        'brief_revision' => 1,
        'bible_revision' => 0,
        'technical_header' => [],
        'learning_objectives' => [],
        'continuity_note' => '',
        'continuity_source_video_ids' => [],
        'sections' => [['number' => '1', 'title' => 'Secreto']],
        'taught_summary' => '',
        'summary_points' => [],
        'recording_notes' => [],
        'verification_checklist' => [],
        'coverage_map' => [],
        'errors_check' => [],
        'notes_excerpt_ids' => [],
        'feedback_note' => 'Más ejemplos',
    ]);

    $response = $this->actingAs($this->author)
        ->getJson(route('course-scripts.videos.versions', [$this->course->uuid, $this->video->uuid]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $version->uuid)
        ->assertJsonPath('data.0.writer_provider', 'openai')
        ->assertJsonPath('data.0.feedback_note', 'Más ejemplos')
        ->assertJsonPath('data.0.is_accepted', false);

    expect($response->json('data.0'))->not->toHaveKeys(['id', 'sections', 'course_video_id']);
});
