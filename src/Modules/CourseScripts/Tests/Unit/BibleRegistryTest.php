<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Services\BibleRegistry;

$bible = [
    'organisations' => [
        ['key' => 'tecnoform', 'name' => 'Tecnoform S.A.', 'role' => 'Empresa del alumno', 'sector' => 'Industria', 'is_primary' => true],
    ],
    'characters' => [['name' => 'Marta', 'role' => 'Operaciones', 'organisation_key' => 'tecnoform']],
    'tone' => 'Cercano',
];

it('registers organisations and characters a practice pack introduces', function () use ($bible): void {
    $result = (new BibleRegistry)->merge(
        $bible,
        [['name' => 'Heliantia Group', 'role' => 'Cliente'], ['name' => 'Transportes Meridional S.L.', 'role' => 'Proveedor A']],
        [['name' => 'Ramón Alcántara', 'role' => 'Comercial', 'organisation' => 'Transportes Meridional S.L.']],
    );

    expect($result['changed'])->toBeTrue()
        ->and(array_column($result['bible']['organisations'], 'name'))->toBe(['Tecnoform S.A.', 'Heliantia Group', 'Transportes Meridional S.L.'])
        ->and(array_column($result['bible']['organisations'], 'is_primary'))->toBe([true, false, false])
        ->and($result['bible']['characters'][1]['organisation_key'])->toBe('transportes_meridional_sl')
        ->and($result['bible']['tone'])->toBe('Cercano');
});

it('does not duplicate a known organisation written differently', function () use ($bible): void {
    $result = (new BibleRegistry)->merge($bible, [['name' => 'TECNOFORM s.a']], [['name' => 'marta']]);

    expect($result['changed'])->toBeFalse()
        ->and($result['bible']['organisations'])->toHaveCount(1)
        ->and($result['bible']['characters'])->toHaveCount(1);
});

it('makes the first organisation primary when the bible had none', function (): void {
    $result = (new BibleRegistry)->merge([], [['name' => 'Lumitec Distribución S.L.']], []);

    expect($result['bible']['organisations'][0]['is_primary'])->toBeTrue()
        ->and((new BibleRegistry)->isKnownOrganisation($result['bible'], 'lumitec distribucion sl'))->toBeTrue();
});
