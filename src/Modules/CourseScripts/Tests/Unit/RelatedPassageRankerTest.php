<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Services\RelatedPassageRanker;

beforeEach(function (): void {
    $this->ranker = new RelatedPassageRanker;
});

it('ranks passages by keyword overlap, best first', function (): void {
    $passages = $this->ranker->rank(
        'Integración Google Drive documentos',
        [
            ['label' => 'video 3', 'text' => 'Cierre del curso y despedida.'],
            ['label' => 'video 5', 'text' => 'Cómo conectar Google Drive y comparar documentos.'],
            ['label' => 'video 7', 'text' => 'Documentos de Google Drive para el informe.'],
        ],
        3,
        2000,
    );

    expect($passages)->toHaveCount(2)
        ->and($passages[0])->toStartWith('video 5')
        ->and($passages[1])->toStartWith('video 7');
});

it('returns nothing when nothing matches', function (): void {
    expect($this->ranker->rank('fotografía analógica', [
        ['label' => 'video 1', 'text' => 'Cómo conectar Google Drive.'],
    ], 3, 2000))->toBe([]);
});

it('returns nothing for empty input or zero budget', function (): void {
    expect($this->ranker->rank('', [['label' => 'v', 'text' => 'algo']], 3, 2000))->toBe([])
        ->and($this->ranker->rank('algo', [], 3, 2000))->toBe([])
        ->and($this->ranker->rank('algo', [['label' => 'v', 'text' => 'algo']], 0, 2000))->toBe([])
        ->and($this->ranker->rank('algo', [['label' => 'v', 'text' => 'algo']], 3, 0))->toBe([]);
});

it('caps long passages so one summary cannot crowd the prompt', function (): void {
    $passages = $this->ranker->rank(
        'conectar Google Drive documentos',
        [['label' => 'video 5', 'text' => 'Google Drive documentos '.str_repeat('relleno ', 500)]],
        1,
        600,
    );

    expect($passages)->toHaveCount(1)
        ->and(mb_strlen($passages[0]))->toBeLessThanOrEqual(700);
});

it('ignores short words and stopwords when extracting terms', function (): void {
    expect($this->ranker->terms('el uso de la y en un'))->toBe([]);
});
