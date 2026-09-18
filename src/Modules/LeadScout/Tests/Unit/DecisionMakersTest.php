<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;
use Modules\LeadScout\Domain\ValueObjects\RoleTaxonomy;

it('classifies decisor titles and discards the rest', function (): void {
    expect(RoleTaxonomy::classify('Fundador & CEO')?->value)->toBe('founder')
        ->and(RoleTaxonomy::classify('CTO')?->value)->toBe('technical_lead')
        ->and(RoleTaxonomy::classify('Sócio-gerente')?->value)->toBe('executive')
        ->and(RoleTaxonomy::classify('Head of Engineering')?->value)->toBe('technical_lead')
        ->and(RoleTaxonomy::classify('Talent Acquisition'))->toBeNull()
        ->and(RoleTaxonomy::classify('Recruiter'))->toBeNull()
        ->and(RoleTaxonomy::classify('RRHH'))->toBeNull()
        ->and(RoleTaxonomy::classify('Desarrollador Senior'))->toBeNull()
        ->and(RoleTaxonomy::classify('Tech Lead'))->toBeNull()
        ->and(RoleTaxonomy::classify('Random Gibberish'))->toBeNull();
});

it('orders contact priority by team size', function (): void {
    expect(array_map(fn ($c) => $c->value, RoleTaxonomy::preferredOrder(10)))->toBe(['founder', 'executive', 'technical_lead'])
        ->and(array_map(fn ($c) => $c->value, RoleTaxonomy::preferredOrder(40)))->toBe(['technical_lead', 'executive', 'founder']);
});

function teamPage(): string
{
    return implode("\n", [
        '# Nuestro equipo',
        '',
        '**Ana Ruiz** — CEO y fundadora',
        'ana@agencia.example',
        '',
        '**João Silva** — CTO',
        'joao@agencia.example',
        '',
        '**María López** — Talent Acquisition',
        '',
        '**Pedro Santos** — Desarrollador Senior',
        '',
        '**Lucía Fernández** — Sócia-gerente',
        'info@agencia.example',
        '',
        'Somos 12 personas.',
    ]);
}

it('extracts decisors with evidence and counts the team', function (): void {
    $result = app(DecisionMakerExtractor::class)->extract(
        [['url' => 'https://agencia.example/equipo', 'markdown' => teamPage()]],
        'agencia.example',
    );

    $names = array_column($result['candidates'], 'name');

    expect($names)->toContain('Ana Ruiz', 'João Silva', 'Lucía Fernández')
        ->and($names)->not->toContain('María López', 'Pedro Santos')
        ->and($result['teamSize'])->toBe(12);
});

it('attaches same-domain emails and drops generic or foreign ones', function (): void {
    $result = app(DecisionMakerExtractor::class)->extract(
        [[
            'url' => 'https://agencia.example/equipo',
            'markdown' => implode("\n", [
                '**Ana Ruiz** — CEO, ana@agencia.example',
                '**João Silva** — CTO, joao@gmail.com',
                '**Lucía Fernández** — Sócia-gerente',
            ]),
        ]],
        'agencia.example',
    );

    $byName = [];

    foreach ($result['candidates'] as $candidate) {
        $byName[$candidate['name']] = $candidate;
    }

    expect($byName['Ana Ruiz']['email'])->toBe('ana@agencia.example')
        ->and($byName['João Silva']['email'])->toBeNull()
        ->and($byName['Lucía Fernández']['email'])->toBeNull();
});

it('skips opposed people and dedupes repeats', function (): void {
    $extractor = app(DecisionMakerExtractor::class);
    $pages = [['url' => 'https://agencia.example/equipo', 'markdown' => "**Ana Ruiz** — CEO\n\n**Ana Ruiz** — CEO"]];

    expect($extractor->extract($pages, 'agencia.example')['candidates'])->toHaveCount(1)
        ->and($extractor->extract(
            $pages,
            'agencia.example',
            [],
            [DecisionMakerExtractor::personHash('Ana Ruiz', 'agencia.example')],
        )['candidates'])->toBeEmpty();
});

it('reads founders from about phrases and json-ld', function (): void {
    $result = app(DecisionMakerExtractor::class)->extract(
        [[
            'url' => 'https://agencia.example/nosotros',
            'markdown' => 'Agencia fundada por Carlos Méndez en 2019. CEO: Laura Vidal.',
        ]],
        'agencia.example',
    );

    expect(array_column($result['candidates'], 'name'))->toContain('Carlos Méndez', 'Laura Vidal');
});
