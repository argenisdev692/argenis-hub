<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;
use Modules\CourseScripts\Tests\Support\FakeStorage;
use Modules\CourseScripts\Tests\Support\IndexFixtures;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = FakeStorage::install();
    $this->author = CourseScriptTestUsers::author();
});

function markdownUpload(string $fixture, string $name = 'indice.md'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents(IndexFixtures::path($fixture)));
}

it('uploads a markdown index with notes and persists the whole course', function (): void {
    $response = $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'title' => 'Excel para comerciales',
        'index' => markdownUpload('index-with-notes.md'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Excel para comerciales')
        ->assertJsonPath('data.language', 'es')
        ->assertJsonCount(2, 'data.blocks')
        ->assertJsonCount(4, 'data.videos')
        ->assertJsonPath('data.videos.0.number', 1)
        ->assertJsonPath('data.videos.0.declared_duration_minutes', 9)
        ->assertJsonPath('data.videos.3.notes', null)
        ->assertJsonPath('data.videos.0.script_status', 'not_started');

    expect($response->json('data.course_notes'))->toContain('Lumitec')
        ->and($response->json('data.videos.0.notes'))->toContain('1.240 filas')
        ->and($response->json('data'))->not->toHaveKey('id');

    $course = CourseEloquentModel::query()->firstOrFail();

    expect($course->user_id)->toBe($this->author->id)
        ->and($course->sourceDocuments()->where('kind', SourceDocumentKind::Index->value)->count())->toBe(1)
        ->and($this->storage->pathsUnder('course-scripts/'.$course->uuid))->toHaveCount(1);
});

it('prefers the typed title and falls back to the index title', function (): void {
    $this->actingAs($this->author)->postJson(route('course-scripts.store'), ['index' => markdownUpload('index-with-notes.md')])
        ->assertCreated()
        ->assertJsonPath('data.title', 'EXCEL PARA EQUIPOS COMERCIALES');
});

it('rejects an index without a title when none is typed', function (): void {
    $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'index' => UploadedFile::fake()->createWithContent('lista.md', "- Primer tema de la lista\n- Segundo tema de la lista\n"),
    ])->assertStatus(422)->assertJsonPath('code', 'title_required');

    expect(CourseEloquentModel::query()->count())->toBe(0);
});

it('uploads a PDF index', function (): void {
    $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'index' => new UploadedFile(IndexFixtures::pdfPath(), 'indice.pdf', 'application/pdf', null, true),
    ])->assertCreated()
        ->assertJsonCount(7, 'data.blocks')
        ->assertJsonCount(48, 'data.videos');
});

it('stores content files and assigns them to a video', function (): void {
    $response = $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'title' => 'Excel',
        'index' => markdownUpload('index-with-notes.md'),
        'contents' => [markdownUpload('content-notes.md', 'apuntes.md'), markdownUpload('content-notes.md', 'buscarx.md')],
        'content_video_numbers' => [null, 4],
    ])->assertCreated();

    $documents = collect($response->json('data.documents'))->where('kind', 'content')->values();
    $videoFour = collect($response->json('data.videos'))->firstWhere('number', 4);

    expect($documents)->toHaveCount(2)
        ->and($documents[0]['video_uuid'])->toBeNull()
        ->and($documents[1]['video_uuid'])->toBe($videoFour['uuid'])
        ->and($documents[0])->not->toHaveKeys(['path', 'checksum', 'extracted_text']);

    $course = CourseEloquentModel::query()->firstOrFail();

    expect($course->sourceDocuments()->where('kind', 'content')->first()->extracted_text)->toContain('BUSCARX');
});

it('flags thin videos for review without dropping them', function (): void {
    $response = $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'index' => markdownUpload('index-cooking.md'),
    ])->assertCreated();

    expect($response->json('data.videos'))->toHaveCount(6)
        ->and($response->json('data.videos_needing_review'))->toBe(6)
        ->and($response->json('data.blocks'))->toBe([])
        ->and($response->json('data.videos.0.block_uuid'))->toBeNull()
        ->and($response->json('data.videos.0.effective_duration_minutes'))->toBe(8);
});

it('rejects a file that is not an index and stores nothing', function (): void {
    $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'title' => 'Memo',
        'index' => UploadedFile::fake()->createWithContent('memo.md', "# A memo\n\nJust a paragraph of prose with no structure at all.\n"),
    ])->assertStatus(422)->assertJsonPath('code', 'unrecognisable_index');

    expect(CourseEloquentModel::query()->count())->toBe(0)
        ->and($this->storage->objects)->toBe([]);
});

it('rejects a wrong file type and an oversized file', function (): void {
    $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'index' => UploadedFile::fake()->image('index.png'),
    ])->assertStatus(422)->assertJsonValidationErrors('index');

    config()->set('course-scripts.uploads.max_kb', 1);

    $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'index' => UploadedFile::fake()->createWithContent('big.md', str_repeat("- Tema de la lista número\n", 200)),
    ])->assertStatus(422)->assertJsonValidationErrors('index');
});

it('rejects too many content files', function (): void {
    config()->set('course-scripts.uploads.max_content_files', 1);

    $this->actingAs($this->author)->postJson(route('course-scripts.store'), [
        'index' => markdownUpload('index-with-notes.md'),
        'contents' => [markdownUpload('content-notes.md', 'a.md'), markdownUpload('content-notes.md', 'b.md')],
    ])->assertStatus(422)->assertJsonValidationErrors('contents');
});

it('writes a metadata-only audit entry', function (): void {
    $this->actingAs($this->author)->postJson(route('course-scripts.store'), ['index' => markdownUpload('index-with-notes.md')])->assertCreated();

    $entry = Activity::query()->where('event', 'course_scripts.course_uploaded')->orWhere('description', 'course_scripts.course_uploaded')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties->get('video_count'))->toBe(4)
        ->and(json_encode($entry->properties))->not->toContain('Lumitec');
});

it('redirects an Inertia form upload to the course page', function (): void {
    $response = $this->actingAs($this->author)->post(route('course-scripts.store'), ['index' => markdownUpload('index-with-notes.md')]);

    $course = CourseEloquentModel::query()->firstOrFail();

    $response->assertRedirect(route('course-scripts.show', $course->uuid));
});
