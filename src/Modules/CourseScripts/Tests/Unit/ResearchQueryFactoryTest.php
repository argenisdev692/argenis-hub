<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Services\ResearchQueryFactory;

it('builds subject queries from the course title and blocks', function (): void {
    $queries = (new ResearchQueryFactory)->subjectQueries('Claude para usuarios', ['Fundamentos', 'Conectores'], 'es', 4);

    expect($queries)->toHaveCount(4)
        ->and($queries[0])->toBe('Claude para usuarios')
        ->and($queries[1])->toBe('Claude para usuarios guía práctica');
});

it('builds focused video queries and fewer when notes are rich', function (): void {
    $factory = new ResearchQueryFactory;

    $thin = $factory->videoQueries('Excel', 'Buscar datos con BUSCARX', 'Búsquedas', 'Sustituir BUSCARV', false, 2);
    $rich = $factory->videoQueries('Excel', 'Buscar datos con BUSCARX', 'Búsquedas', 'Sustituir BUSCARV', true, 2);

    expect($thin)->toBe(['Excel Buscar datos con BUSCARX', 'Búsquedas Buscar datos con BUSCARX'])
        ->and($rich)->toBe(['Excel Buscar datos con BUSCARX']);
});

it('strips search operators and markup from author text', function (): void {
    $clean = (new ResearchQueryFactory)->sanitise('site:evil.test "exact" OR -minus <script> Claude');

    expect($clean)->toBe('exact minus script Claude');
});

it('returns nothing for an empty title', function (): void {
    expect((new ResearchQueryFactory)->videoQueries('Curso', '  ', null, null, false, 2))->toBe([])
        ->and((new ResearchQueryFactory)->subjectQueries('', [], 'es', 4))->toBe([]);
});
