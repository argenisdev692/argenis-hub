<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Services\ContactDataValidator;
use Modules\CourseScripts\Domain\Services\InstructorNoteCoverageValidator;
use Modules\CourseScripts\Domain\Services\TableArithmeticValidator;
use Modules\CourseScripts\Tests\Support\CanonicalPracticePackFixture;

it('reads Spanish and English number formats', function (): void {
    $tables = new TableArithmeticValidator;

    expect($tables->number('16.280 €'))->toBe(16280.0)
        ->and($tables->number('7,40 €/envío'))->toBe(7.4)
        ->and($tables->number('1,234.50'))->toBe(1234.5)
        ->and($tables->number('16,280'))->toBe(16280.0)
        ->and($tables->number('—'))->toBeNull();
});

it('accepts the exact totals of the author\'s sample proposals', function (): void {
    $pack = CanonicalPracticePackFixture::artifacts();
    $tables = new TableArithmeticValidator;

    expect($tables->violations('Propuesta_Logistica_ProveedorA_2026', $pack['Propuesta_Logistica_ProveedorA_2026']['content_blocks']))->toBe([])
        ->and($tables->violations('Propuesta_Logistica_ProveedorB_2026', $pack['Propuesta_Logistica_ProveedorB_2026']['content_blocks']))->toBe([]);
});

it('flags a total row that does not add up', function (): void {
    $blocks = CanonicalPracticePackFixture::artifacts()['Propuesta_Logistica_ProveedorA_2026']['content_blocks'];

    foreach ($blocks as $index => $block) {
        if (($block['total_row'] ?? false) === true) {
            $last = count($block['table_rows']) - 1;
            $blocks[$index]['table_rows'][$last][3] = '28.956 €';
        }
    }

    expect((new TableArithmeticValidator)->violations('A', $blocks))->toHaveCount(1);
});

it('rejects real email domains and links in artifacts', function (): void {
    $validator = new ContactDataValidator(['gmail.com', 'microsoft.com']);

    $blocks = [
        ['type' => 'footer', 'text' => 'Contacto: ramon@gmail.com · https://example.com/oferta'],
        ['type' => 'key_values', 'pairs' => [['key' => 'Email', 'value' => 'c.moya@lpnorte.es']]],
    ];

    expect($validator->violations('A', $blocks))->toHaveCount(2)
        ->and($validator->violations('B', [['type' => 'footer', 'text' => 'r.alcantara@t-meridional.es']]))->toBe([]);
});

it('requires the instructor note to explain every designed contrast', function (): void {
    $validator = new InstructorNoteCoverageValidator;
    $plan = CanonicalPracticePackFixture::plan();

    expect($validator->violations($plan['instructor_note'], $plan['contrasts']))->toBe([])
        ->and($validator->violations('Las propuestas son distintas.', $plan['contrasts']))->not->toBe([]);
});
