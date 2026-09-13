<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\ValueObjects\ParsedPoint;
use Modules\CourseScripts\Infrastructure\Parsing\MarkdownIndexParser;
use Modules\CourseScripts\Tests\Support\IndexFixtures;

/**
 * The parser must accept an index on ANY subject, from ANY author, in ANY of
 * the shapes people actually write them (spec FR-2, FR-2a).
 *
 * The reference course is one input among many, so it gets the richest
 * assertions but not special treatment: the Cursor, Copilot and bare-list cases
 * below are equally first-class, and they are the ones that caught the
 * hardcoding an earlier version shipped with.
 */
$parse = static fn (string $text) => (new MarkdownIndexParser)->parseText($text);

// ---------------------------------------------------------------------------
// The reference course — a rich, grouped, Spanish index
// ---------------------------------------------------------------------------

it('recovers the reference course header', function (): void {
    $index = IndexFixtures::parsedSample();

    expect($index->title)->toBe('CLAUDE PARA USUARIOS')
        ->and($index->declaredTotalMinutes)->toBe(420)
        ->and($index->language)->toBe('es');
});

it('recovers all seven groups in order with their durations', function (): void {
    $index = IndexFixtures::parsedSample();

    expect($index->groupCount())->toBe(7)
        ->and(array_map(fn ($g) => $g->number, $index->groups))->toBe([1, 2, 3, 4, 5, 6, 7])
        ->and($index->groups[0]->title)->toBe('Fundamentos y uso profesional de Claude')
        ->and($index->groups[0]->declaredDurationMinutes)->toBe(52)
        ->and($index->groups[6]->declaredDurationMinutes)->toBe(53)
        ->and($index->groups[0]->isImplicit)->toBeFalse();
});

it('recovers all 48 points with contiguous numbering', function (): void {
    $index = IndexFixtures::parsedSample();

    expect($index->pointCount())->toBe(48)
        ->and(array_map(fn ($p) => $p->position, $index->points))->toBe(range(1, 48));
});

it('recovers every brief field for a spot-checked point', function (): void {
    $point = collect(IndexFixtures::parsedSample()->points)->firstWhere('position', 39);

    expect($point->title)->toBe('Uso de Claude en reuniones: antes, durante y después')
        ->and($point->groupNumber)->toBe(6)
        ->and($point->declaredDurationMinutes)->toBe(9)
        ->and($point->topic)->toBe('Reuniones')
        ->and($point->objective)->toContain('preparar reuniones')
        ->and($point->learningAreas)->not->toBeEmpty()
        ->and($point->audienceObjectives)->not->toBeEmpty()
        ->and($point->mandatoryContent)->toHaveCount(5)
        ->and($point->errorsToAvoid)->not->toBeEmpty()
        ->and($point->expectedResult)->toContain('reuniones más productivas')
        ->and($point->hasFullBrief())->toBeTrue()
        ->and($point->isThin())->toBeFalse();
});

it('sums point durations to the declared course total', function (): void {
    $index = IndexFixtures::parsedSample();

    expect($index->summedPointMinutes())->toBe(420)
        ->and($index->summedPointMinutes())->toBe($index->declaredTotalMinutes);
});

// ---------------------------------------------------------------------------
// Arbitrary subjects — the cases the module actually has to accept
// ---------------------------------------------------------------------------

it('parses an English Cursor index with modules instead of blocks', function () use ($parse): void {
    $index = $parse(<<<'MD'
        # Mastering Cursor

        ## MODULE 1 - Getting productive (40 min)

        | # | Lesson | Topic | Duration |
        |---|--------|-------|----------|
        | 1 | Installing and configuring Cursor | Setup | 8 min |
        | 2 | Tab completion and inline edits | Editing | 10 min |
        | 3 | Multi-file edits with Composer | Composer | 12 min |

        ## MODULE 2 - Working at scale (30 min)

        | # | Lesson | Topic | Duration |
        |---|--------|-------|----------|
        | 4 | Codebase-wide context and @ mentions | Context | 15 min |
        | 5 | Rules files and team conventions | Rules | 15 min |
        MD);

    expect($index->title)->toBe('Mastering Cursor')
        ->and($index->language)->toBe('en')
        ->and($index->groupCount())->toBe(2)
        ->and($index->pointCount())->toBe(5)
        ->and($index->points[2]->title)->toBe('Multi-file edits with Composer')
        ->and($index->points[2]->declaredDurationMinutes)->toBe(12)
        ->and($index->points[2]->groupNumber)->toBe(1)
        ->and($index->points[4]->groupNumber)->toBe(2)
        ->and($index->summedPointMinutes())->toBe(60);
});

it('parses a Microsoft 365 Copilot index that is nothing but numbered headings', function () use ($parse): void {
    // No groups, no table, no durations, no brief. The common case.
    $index = $parse(<<<'MD'
        # Microsoft 365 Copilot for business users

        ## 1. What Copilot can and cannot do
        ## 2. Copilot in Word: drafting and rewriting
        ## 3. Copilot in Excel: analysing a workbook
        ## 4. Copilot in Teams: meeting recaps
        ## 5. Data boundaries and what Copilot can see
        MD);

    expect($index->pointCount())->toBe(5)
        ->and($index->points[0]->title)->toBe('What Copilot can and cannot do')
        ->and($index->points[3]->title)->toBe('Copilot in Teams: meeting recaps')
        ->and($index->points[4]->position)->toBe(5);

    // No grouping in the source, so one implicit group covers everything.
    expect($index->groupCount())->toBe(1)
        ->and($index->groups[0]->isImplicit)->toBeTrue()
        ->and($index->points[0]->groupNumber)->toBe(1);

    // Every point is thin — and that is valid input, not an error.
    expect($index->thinPoints())->toHaveCount(5)
        ->and($index->isEmpty())->toBeFalse();
});

it('parses a bare bulleted list of topics', function () use ($parse): void {
    $index = $parse(<<<'MD'
        # Kubernetes for backend developers

        - Pods, deployments and services
        - ConfigMaps and secrets in practice
        - Horizontal pod autoscaling
        - Debugging a CrashLoopBackOff
        MD);

    expect($index->pointCount())->toBe(4)
        ->and($index->points[0]->title)->toBe('Pods, deployments and services')
        ->and($index->points[3]->title)->toBe('Debugging a CrashLoopBackOff')
        ->and($index->groups[0]->isImplicit)->toBeTrue();
});

it('parses a plain numbered list with no headings at all', function () use ($parse): void {
    $index = $parse(<<<'TXT'
        Curso de facturación electrónica

        1. Qué es la factura electrónica
        2. Requisitos legales por país
        3. Emitir tu primera factura
        TXT);

    expect($index->pointCount())->toBe(3)
        ->and($index->points[1]->title)->toBe('Requisitos legales por país')
        ->and($index->language)->toBe('es');
});

it('does not report a total duration when the index declares none', function () use ($parse): void {
    $index = $parse("# Topic list\n\n## 1. First thing\n## 2. Second thing\n");

    expect($index->summedPointMinutes())->toBeNull()
        ->and($index->declaredTotalMinutes)->toBeNull();
});

// ---------------------------------------------------------------------------
// Boundaries
// ---------------------------------------------------------------------------

it('flags a thin point without dropping it', function () use ($parse): void {
    $index = $parse(<<<'MD'
        # Curso de prueba

        ### BLOQUE 1 – Bloque único (9 min)

        | Nº | Vídeo | Tema | Duración |
        |----|-------|------|----------|
        | 1 | Vídeo completo | Tema | 5 min |
        | 2 | Vídeo incompleto | Tema | 4 min |

        ## VÍDEO 1 – Vídeo completo

        **Objetivo del vídeo:** Hacer algo concreto.

        **Contenido mínimo obligatorio**

        1. Primer punto
        2. Segundo punto

        **Resultado esperado:** El alumno consigue algo.

        ## VÍDEO 2 – Vídeo incompleto

        Sin brief.
        MD);

    expect($index->pointCount())->toBe(2);

    $thin = $index->thinPoints();

    expect($thin)->toHaveCount(1)
        ->and($thin[0]->position)->toBe(2)
        ->and($index->points[0]->hasFullBrief())->toBeTrue();
});

it('treats prose with no enumerable structure as empty', function () use ($parse): void {
    $index = $parse(
        "# A memo\n\nThis is a paragraph of prose. It has no list, no headings "
        ."beyond the title, and nothing an author could record a video about.\n"
    );

    expect($index->isEmpty())->toBeTrue()
        ->and($index->pointCount())->toBe(0);
});

it('accepts a de-marked index the way a typeset PDF delivers it', function () use ($parse): void {
    $index = $parse(<<<'TXT'
        CLAUDE PARA USUARIOS
        Duración total: 9 minutos

        BLOQUE 1 - Bloque único (9 min)

        1 Vídeo de prueba Tema 9 min

        VÍDEO 1 - Vídeo de prueba

        Objetivo del vídeo: Demostrar algo en pantalla.

        Contenido mínimo obligatorio

        1. Un punto obligatorio

        Resultado esperado: El alumno lo consigue.
        TXT);

    expect($index->groupCount())->toBe(1)
        ->and($index->pointCount())->toBe(1)
        ->and($index->points[0]->declaredDurationMinutes)->toBe(9)
        ->and($index->points[0]->objective)->toContain('Demostrar algo')
        ->and($index->points[0]->mandatoryContent)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Author notes (FR-4b, FR-4c)
// ---------------------------------------------------------------------------

it('keeps the free text under a point as that point\'s notes', function (): void {
    $index = IndexFixtures::parsed('index-with-notes.md');
    $points = collect($index->points)->keyBy('position');

    expect($index->pointCount())->toBe(4)
        ->and($points[1]->notes)->toContain('1.240 filas')
        ->and($points[1]->notes)->toContain('se amplía sola')
        ->and($points[2]->notes)->toContain('FECHANUMERO')
        ->and($points[3]->notes)->toStartWith('Apuntes del autor: comparar la zona Norte')
        ->and($points[4]->notes)->toBeNull();
});

it('never duplicates brief fields into the notes', function (): void {
    $point = collect(IndexFixtures::parsed('index-with-notes.md')->points)->firstWhere('position', 1);

    expect($point->objective)->toBe('Enseñar a convertir la hoja de pedidos en una tabla estructurada.')
        ->and($point->mandatoryContent)->toHaveCount(2)
        ->and($point->errorsToAvoid)->toBe(['Explicar tablas sin abrir Excel'])
        ->and($point->expectedResult)->toBe('El alumno convierte su hoja en tabla y entiende las referencias estructuradas.')
        ->and($point->notes)->not->toContain('Objetivo')
        ->and($point->notes)->not->toContain('Ctrl+T')
        ->and($point->notes)->not->toContain('Resultado esperado');
});

it('keeps the preamble as course notes and drops the structure around it', function (): void {
    $notes = IndexFixtures::parsed('index-with-notes.md')->courseNotes;

    expect($notes)->toContain('Lumitec Distribución S.L.')
        ->and($notes)->toContain('funciones dinámicas')
        ->and($notes)->not->toContain('TABLA DE CONTENIDOS')
        ->and($notes)->not->toContain('BLOQUE')
        ->and($notes)->not->toContain('Duración total')
        ->and($notes)->not->toContain('|');
});

it('does not invent notes for an index that has none', function (): void {
    $index = IndexFixtures::parsedSample();

    expect($index->courseNotes)->toBeNull()
        ->and(collect($index->points)->filter(fn ($point) => $point->notes !== null))->toBeEmpty();
});

it('captures notes under plain heading points', function () use ($parse): void {
    $index = $parse(<<<'MD'
        # Copilot quick wins

        ## 1. Summarise a long email thread
        Use the thread with the supplier about late deliveries.

        ## 2. Draft a reply in Outlook
        MD);

    expect($index->points[0]->notes)->toBe('Use the thread with the supplier about late deliveries.')
        ->and($index->points[1]->notes)->toBeNull()
        ->and($index->courseNotes)->toBeNull();
});

it('does not flag a thin point for review when its notes carry substance', function (): void {
    $withNotes = new ParsedPoint(position: 1, title: 'Tema', notes: str_repeat('Apunte sustancial. ', 20));
    $withoutNotes = new ParsedPoint(position: 2, title: 'Tema', notes: 'Breve.');

    expect($withNotes->isThin())->toBeTrue()
        ->and($withNotes->needsReview())->toBeFalse()
        ->and($withoutNotes->needsReview())->toBeTrue();
});
