<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Services\CallEstimator;
use Modules\CourseScripts\Domain\Services\ContinuityContextBuilder;
use Modules\CourseScripts\Domain\Services\DocumentNameFactory;
use Modules\CourseScripts\Domain\Services\NotesExcerptSelector;

function courseVideos(): array
{
    return [
        ['id' => 11, 'number' => 1, 'title' => 'Intro', 'block_id' => 1, 'objective' => 'Presentar', 'taught_summary' => 'Qué es Claude.'],
        ['id' => 12, 'number' => 2, 'title' => 'Planes', 'block_id' => 1, 'objective' => 'Comparar planes', 'taught_summary' => null],
        ['id' => 13, 'number' => 3, 'title' => 'Seguridad', 'block_id' => 2, 'objective' => null, 'taught_summary' => null],
        ['id' => 14, 'number' => 4, 'title' => 'Colaboración', 'block_id' => 2, 'objective' => null, 'taught_summary' => 'Compartir proyectos.'],
    ];
}

it('frames the first video of the course', function (): void {
    $context = (new ContinuityContextBuilder)->build(11, courseVideos(), [1 => ['number' => 1, 'title' => 'Fundamentos'], 2 => ['number' => 2, 'title' => 'Equipo']]);

    expect($context->isFirstOfCourse)->toBeTrue()
        ->and($context->predecessors)->toBe([])
        ->and($context->isProvisional)->toBeFalse()
        ->and($context->nextVideoNumber)->toBe(2)
        ->and($context->positionLabel('es'))->toBe('1 de 2 del bloque');
});

it('marks continuity provisional when a predecessor has no script yet', function (): void {
    $context = (new ContinuityContextBuilder(window: 3))->build(13, courseVideos(), [1 => ['number' => 1, 'title' => 'Fundamentos'], 2 => ['number' => 2, 'title' => 'Equipo']]);

    expect($context->isFirstOfBlock)->toBeTrue()
        ->and($context->blockTitle)->toBe('Equipo')
        ->and(array_column($context->predecessors, 'number'))->toBe([1, 2])
        ->and($context->predecessors[1]['taught'])->toBe('Comparar planes')
        ->and($context->isProvisional)->toBeTrue()
        ->and($context->sourceVideoIds)->toBe([11, 12]);
});

it('bounds the continuity window and knows the last video', function (): void {
    $context = (new ContinuityContextBuilder(window: 2))->build(14, courseVideos(), []);

    expect(array_column($context->predecessors, 'number'))->toBe([2, 3])
        ->and($context->hasNextVideo())->toBeFalse();
});

it('puts the video notes first and ranks course material by relevance', function (): void {
    $excerpts = (new NotesExcerptSelector(budgetChars: 2000, maxExcerpts: 4))->select(
        focusText: 'Buscar datos con BUSCARX en la hoja de tarifas',
        videoNotes: 'Usar la hoja de tarifas de Lumitec.',
        assignedDocuments: [],
        courseNotes: "Público: comerciales.\n\nEvitar fórmulas matriciales antiguas en todos los ejemplos.",
        unassignedDocuments: [['id' => 'doc-1', 'name' => 'apuntes.md', 'text' => (string) file_get_contents(__DIR__.'/../Fixtures/content-notes.md')]],
    );

    expect($excerpts[0]->sourceId)->toBe('video_notes')
        ->and($excerpts[1]->sourceId)->toBe('document:doc-1')
        ->and($excerpts[1]->text)->toContain('BUSCARX busca');
});

it('respects the notes budget', function (): void {
    $excerpts = (new NotesExcerptSelector(budgetChars: 50))->select('tema', str_repeat('a', 500), [], null, []);

    expect($excerpts)->toHaveCount(1)->and(mb_strlen($excerpts[0]->text))->toBe(50);
});

it('estimates calls with and without the second review', function (): void {
    $estimator = new CallEstimator(firecrawlEnabled: false);

    $plain = $estimator->estimate([9], withReview: false, needsBible: true, needsSubjectResearch: true, aiCeiling: 900, researchCeiling: 250);
    $reviewed = $estimator->estimate([9], withReview: true, needsBible: true, needsSubjectResearch: true, aiCeiling: 900, researchCeiling: 250);

    // 1 bible + (1 outline + 6 sections + 1 closing + 1.2 artifacts) = 10.2 → 11
    expect($plain->aiWriteCalls)->toBe(11)
        ->and($plain->aiReviewCalls)->toBe(0)
        ->and($plain->researchCalls)->toBe(6)
        ->and($reviewed->aiReviewCalls)->toBe(3)
        ->and($reviewed->aiCalls())->toBeGreaterThan($plain->aiCalls())
        ->and($plain->fits())->toBeTrue();
});

it('flags an estimate above the ceiling', function (): void {
    $estimate = (new CallEstimator)->estimate(array_fill(0, 48, 9), true, false, false, aiCeiling: 100, researchCeiling: 250);

    expect($estimate->fits())->toBeFalse();
});

it('names deliverables like the author does', function (): void {
    $names = new DocumentNameFactory;

    expect($names->script(9))->toBe('Guion_Video_09')
        ->and($names->promptsSheet(39))->toBe('Prompts_Video_39')
        ->and($names->practiceDocument('Reunión', 39))->toBe('Practica_Reunion_Guion_39')
        ->and($names->artifactFile('Propuesta_Logistica_ProveedorA_2026.pdf'))->toBe('Propuesta_Logistica_ProveedorA_2026')
        ->and($names->artifactFile('../../etc/passwd'))->toBe('Etc_Passwd')
        ->and($names->folder('bloque', 7, 'Seguridad, colaboración y adopción'))->toBe('bloque-07-seguridad-colaboracion-y-adopcion');
});
