<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Services\PersonalDataScrubber;

it('removes emails, phones, testimonials, authors and team cards', function (): void {
    $text = implode("\n", [
        'Nuestros servicios de desarrollo Laravel para agencias.',
        'Escríbenos a hola@agencia.es o llama al +34 910 123 456.',
        '"Gran trabajo en equipo." — María García, CEO de Cliente S.L.',
        'Por Ana Ruiz',
        'Ana Ruiz es la autora de este post.',
        '**João Silva** — CTO',
        'Precios desde 2026 con soporte SLA.',
    ]);

    $scrubbed = app(PersonalDataScrubber::class)->scrub($text, ['João Silva']);

    expect($scrubbed)->not->toContain('hola@agencia.es', '+34 910 123 456', 'María García', 'Ana Ruiz', 'João Silva')
        ->and($scrubbed)->toContain('[EMAIL]', '[TELÉFONO]', '[PERSONA]')
        ->and($scrubbed)->toContain('desarrollo Laravel', '2026', 'soporte SLA');
});
