<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CourseScripts\Application\Commands\BuildDeliverablesHandler;
use Modules\CourseScripts\Application\Commands\GenerateVideoScriptHandler;
use Modules\CourseScripts\Application\Generation\GenerateVideoScriptCommand;
use Modules\CourseScripts\Domain\ValueObjects\PracticeDocument;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Rendering\MarkdownDocumentRenderer;
use Modules\CourseScripts\Tests\Support\CanonicalPracticePackFixture;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;
use Modules\CourseScripts\Tests\Support\FakeResearch;
use Modules\CourseScripts\Tests\Support\FakeStorage;
use Modules\CourseScripts\Tests\Support\GenerationScenario;
use Modules\CourseScripts\Tests\Support\RecordingAiClient;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

/**
 * Markdown, PDF, prompts sheet, practice pack, practice files and ZIP
 * (US-6, US-7, US-8 · FR-35a, FR-36e, FR-45…FR-48a).
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = FakeStorage::install();
    FakeResearch::install();
    $this->author = CourseScriptTestUsers::author();
    $this->course = CourseEloquentModel::factory()->withBible()->withVideos(2)->create(['user_id' => $this->author->id, 'title' => 'Claude para Usuarios']);
    $this->video = $this->course->videos()->where('number', 1)->firstOrFail();

    RecordingAiClient::install(GenerationScenario::payloads([1]));
    $result = app(GenerateVideoScriptHandler::class)->handle(new GenerateVideoScriptCommand($this->course->id, $this->video->id, 'openai'));
    $this->version = CourseScriptVersionEloquentModel::query()->findOrFail($result->scriptVersionId);
    app(BuildDeliverablesHandler::class)->handle($this->version);
});

function deliverableText(object $test, string $type, string $format, string $file = ''): string
{
    $deliverable = CourseDeliverableEloquentModel::query()
        ->where('course_script_version_id', $test->version->id)
        ->where('document_type', $type)
        ->where('format', $format)
        ->where('artifact_file_name', $file)
        ->firstOrFail();

    return (string) $test->storage->get($deliverable->path);
}

it('renders the script in the reference layout', function (): void {
    $markdown = deliverableText($this, 'script', 'md');

    expect($markdown)->toContain('# GUIÓN – VÍDEO 1 — Vídeo 1')
        ->and($markdown)->toContain('## INFORMACIÓN TÉCNICA')
        ->and($markdown)->toContain('## OBJETIVOS DE APRENDIZAJE')
        ->and($markdown)->toContain('**Continuidad:**')
        ->and($markdown)->toContain('## 4. COMPARAR DOS PROPUESTAS (3 minutos) · DEMO 2')
        ->and($markdown)->toContain('### 4.1. Tabla comparativa')
        ->and($markdown)->toContain('**PROMPT:**')
        ->and($markdown)->toContain('**MOSTRAR EN PANTALLA:**')
        ->and($markdown)->toContain('**TABLA EN PANTALLA:**')
        ->and($markdown)->toContain('## RESUMEN')
        ->and($markdown)->toContain('**PRÓXIMO VÍDEO:**')
        ->and($markdown)->toContain('## NOTAS TÉCNICAS PARA LA GRABACIÓN')
        ->and($markdown)->toContain('## VERIFICACIÓN FINAL')
        ->and($markdown)->toContain('- [ ] DEMO 2 genera la tabla comparativa')
        ->and(deliverableText($this, 'script', 'pdf'))->toStartWith('%PDF');
});

it('lists every on-screen prompt in order with the practice files to have ready', function (): void {
    $sheet = deliverableText($this, 'prompts', 'md');

    expect($sheet)->toContain('# PROMPTS EN PANTALLA · VÍDEO 1')
        ->and(strpos($sheet, 'Resume en cinco puntos'))->toBeLessThan(strpos($sheet, 'Compara las dos propuestas'))
        ->and($sheet)->toContain('**Tener preparado antes:** '.CanonicalPracticePackFixture::FILE_A.', '.CanonicalPracticePackFixture::FILE_B);

    // Every prompt in the sheet appears verbatim in the script (SC-7).
    foreach ([...$this->version->sections] as $section) {
        foreach ($section['segments'] as $segment) {
            if ($segment['type'] === 'on_screen_prompt') {
                expect($sheet)->toContain($segment['prompt']);
            }
        }
    }
});

it('renders the practice pack like the author\'s sample, plus one file per artifact', function (): void {
    $practice = deliverableText($this, 'practice', 'md');
    $fileA = deliverableText($this, 'practice_file', 'md', CanonicalPracticePackFixture::FILE_A);

    expect($practice)->toContain('# DOCUMENTO DE PRÁCTICA · VÍDEO 01 — Vídeo 1')
        ->and($practice)->toContain('**Archivos:** '.CanonicalPracticePackFixture::FILE_A.'.pdf, '.CanonicalPracticePackFixture::FILE_B.'.pdf')
        ->and($practice)->toContain('**Usar en:** DEMO 2 (Sección 4)')
        ->and($practice)->toContain('**NOTA PARA EL INSTRUCTOR:**')
        ->and($practice)->toContain('| Precio mensual | 28.856 € | 32.252 € | A es más barato |')
        ->and($practice)->toContain('Datos ficticios')
        ->and($fileA)->toContain('# PROPUESTA DE SERVICIO LOGÍSTICO — PROVEEDOR A')
        ->and($fileA)->toContain('| **TOTAL MENSUAL ESTIMADO** |')
        ->and($fileA)->not->toContain('PROVEEDOR B')
        ->and(deliverableText($this, 'practice_file', 'pdf', CanonicalPracticePackFixture::FILE_B))->toStartWith('%PDF');
});

it('keeps Spanish accents in the PDF text layer', function (): void {
    $pdfPath = tempnam(sys_get_temp_dir(), 'cs-pdf-');
    file_put_contents($pdfPath, deliverableText($this, 'script', 'pdf'));

    try {
        $text = (new Parser)->parseFile($pdfPath)->getText();
    } finally {
        @unlink($pdfPath);
    }

    expect($text)->toContain('INFORMACIÓN TÉCNICA')->and($text)->toContain('VERIFICACIÓN FINAL');
});

it('escapes table-breaking characters coming from model output', function (): void {
    $markdown = (new MarkdownDocumentRenderer)->practiceFile(
        new PracticeDocument('es', 1, 'V', 'Practica', 'H', '', '', [], '', [], [[
            'file_name' => 'X',
            'content_blocks' => [CanonicalPracticePackFixture::table(['A', 'B'], [["x | y\nz", '1']], false)],
        ]]),
        'X',
    );

    expect($markdown)->toContain('| x \| y z | 1 |');
});

it('downloads the course ZIP with the documented layout', function (): void {
    $response = $this->actingAs($this->author)->get(route('course-scripts.bundle', $this->course->uuid));

    $response->assertOk()->assertHeader('content-type', 'application/zip');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $entries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entries[] = $zip->getNameIndex($index);
    }

    $readme = $zip->getFromName('claude-para-usuarios/00-curso/README.md');
    $zip->close();

    $folder = 'claude-para-usuarios/bloque-01-bloque-1/video-01-video-1/';

    expect($entries)->toContain('claude-para-usuarios/00-curso/README.md')
        ->and($entries)->toContain('claude-para-usuarios/00-curso/Biblia.md')
        ->and($entries)->toContain('claude-para-usuarios/00-curso/Indice.md')
        ->and($entries)->toContain($folder.'Guion_Video_01.md')
        ->and($entries)->toContain($folder.'Guion_Video_01.pdf')
        ->and($entries)->toContain($folder.'Prompts_Video_01.pdf')
        ->and($entries)->toContain($folder.'Practica_Google_Workspace_Guion_01.pdf')
        ->and($entries)->toContain($folder.'archivos/'.CanonicalPracticePackFixture::FILE_A.'.pdf')
        ->and($entries)->toHaveCount(13)
        ->and($readme)->toContain('| 2 | Vídeo 2 | not_started |');
});

it('downloads one video\'s ZIP and signed links to single files', function (): void {
    $response = $this->actingAs($this->author)->get(route('course-scripts.videos.bundle', [$this->course->uuid, $this->video->uuid]));
    $response->assertOk();

    $deliverable = CourseDeliverableEloquentModel::query()->where('course_script_version_id', $this->version->id)->firstOrFail();

    $this->actingAs($this->author)->getJson(route('course-scripts.deliverables.download', $deliverable->uuid))
        ->assertOk()
        ->assertJsonPath('data.url', fn (string $url): bool => str_starts_with($url, 'https://storage.test/') && str_contains($url, 'signature='));
});

it('refuses an empty bundle and foreign downloads', function (): void {
    $other = CourseEloquentModel::factory()->withVideos(1)->create(['user_id' => $this->author->id]);

    $this->actingAs($this->author)->getJson(route('course-scripts.bundle', $other->uuid))->assertStatus(409)->assertJsonPath('code', 'nothing_generated');

    $deliverable = CourseDeliverableEloquentModel::query()->firstOrFail();
    $this->actingAs(CourseScriptTestUsers::author())->getJson(route('course-scripts.deliverables.download', $deliverable->uuid))->assertNotFound();
    $this->actingAs(CourseScriptTestUsers::author())->getJson(route('course-scripts.bundle', $this->course->uuid))->assertNotFound();
});
