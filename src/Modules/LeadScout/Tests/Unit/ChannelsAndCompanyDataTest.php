<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Services\ContactChannelDetector;
use Modules\LeadScout\Domain\Services\PublicCompanyDataExtractor;

function channelPage(string $url, string $markdown, ?array $forms = null): array
{
    return ['url' => $url, 'markdown' => $markdown, 'forms_summary' => $forms];
}

function contactForms(): array
{
    return ['forms' => [[
        'fields' => ['nombre', 'email', 'mensaje'],
        'has_textarea' => true,
        'has_captcha' => false,
        'action_host' => 'agencia.example',
    ]]];
}

it('detects contact, careers, freelance, partner and email channels', function (): void {
    $result = app(ContactChannelDetector::class)->detect(
        [
            channelPage(
                'https://agencia.example/contacto',
                "Escríbenos. Trabajamos con freelancers y colaboradores externos.\nEscríbenos a info@agencia.example.",
                contactForms(),
            ),
            channelPage(
                'https://agencia.example/trabaja-con-nosotros',
                'Únete al equipo.',
                ['forms' => [[
                    'fields' => ['nombre', 'email', 'cv', 'mensaje'],
                    'has_textarea' => true,
                    'has_captcha' => true,
                    'action_host' => 'agencia.example',
                ]]],
            ),
            channelPage(
                'https://agencia.example/partners',
                'Nuestro partner program de marca blanca para agencias.',
            ),
        ],
        'agencia.example',
    );

    $byType = [];

    foreach ($result['channels'] as $channel) {
        $byType[$channel['type']][] = $channel;
    }

    expect($byType)->toHaveKeys(['contact_form', 'freelance_call', 'careers_form', 'partner_page', 'generic_email'])
        ->and($byType['careers_form'][0]['audience'])->toBe('hr_recruiting')
        ->and($byType['careers_form'][0]['has_captcha'])->toBeTrue()
        ->and($byType['generic_email'][0]['generic_email'])->toBe('info@agencia.example')
        ->and($result['impliedSignals'])->not->toBeEmpty();
});

it('resolves the job offer as a channel and never stores phones', function (): void {
    $result = app(ContactChannelDetector::class)->detect(
        [channelPage('https://agencia.example/contacto', 'Llámanos al +34 910 111 222 o escribe a hola@agencia.example.', contactForms())],
        'agencia.example',
        ['url' => 'https://ofertas.example.com/1', 'apply_email' => null],
    );

    $flat = json_encode($result);

    expect($flat)->not->toContain('+34 910 111 222')
        ->and($flat)->toContain('job_posting_apply', 'hola@agencia.example');
});

it('ignores nominative and foreign emails', function (): void {
    $result = app(ContactChannelDetector::class)->detect(
        [channelPage('https://agencia.example/contacto', 'Escríbenos a joao@agencia.example o a info@gmail.com.', contactForms())],
        'agencia.example',
    );

    $emails = array_column($result['channels'], 'generic_email');

    expect($emails)->not->toContain('joao@agencia.example', 'info@gmail.com');
});

it('extracts public company data with evidence and detects freelancers', function (): void {
    $jsonLd = '<script type="application/ld+json">{"@type":"Organization","legalName":"Agencia Ejemplo S.L.","taxID":"B12345678","foundingDate":"2018-05-01","address":{"addressLocality":"Madrid"}}</script>';

    $result = app(PublicCompanyDataExtractor::class)->extract([
        ['url' => 'https://agencia.example/', 'page_type' => 'home', 'markdown' => $jsonLd."\nBienvenidos a nuestra agencia."],
        ['url' => 'https://agencia.example/aviso-legal', 'page_type' => 'legal', 'markdown' => "Agencia Ejemplo, S.L.\nNIF B12345678\nInscrita en el Registro Mercantil de Madrid, Tomo 100."],
        ['url' => 'https://agencia.example/servicios', 'page_type' => 'services', 'markdown' => "## Desarrollo Laravel\n## Soporte y mantenimiento"],
    ]);

    expect($result['data']['legal_name'])->toBe('Agencia Ejemplo S.L.')
        ->and($result['data']['tax_id'])->toBe('B12345678')
        ->and($result['data']['founded_year'])->toBe(2018)
        ->and($result['data']['city'])->toBe('Madrid')
        ->and($result['data']['services'])->toContain('Desarrollo Laravel')
        ->and($result['evidence']['legal_name']['url'])->toContain('agencia.example')
        ->and($result['is_natural_person'])->toBeFalse();
});

it('resolves the company name from the footer legal suffix, never from a title', function (): void {
    $result = app(PublicCompanyDataExtractor::class)->extract([
        [
            'url' => 'https://agencia.example/blog/laravel-2026',
            'page_type' => 'blog',
            'markdown' => "10 trucos de Laravel que debes conocer en 2026\n© 2026 Agencia Ejemplo, S.L.\nTodos los derechos reservados. Madrid",
        ],
    ]);

    // The post title is not a company identity; the footer's legal suffix is.
    expect($result['data']['legal_name'])->toBe('© 2026 Agencia Ejemplo, S.L.')
        ->and($result['data']['city'])->toBe('Madrid')
        ->and($result['evidence']['legal_name']['url'])->toContain('agencia.example');
});

it('never flags an agency that advertises working with freelancers', function (): void {
    // 2026-09-25 regression: a whole-site search for «freelancer»/«autónomo»
    // discarded six real agencies (mitsoftware, modulardigital, acquaintsoft…)
    // because they say "trabajamos con freelancers" / "servicios para
    // autónomos" — which is precisely the buying signal we are looking for.
    $result = app(PublicCompanyDataExtractor::class)->extract([
        ['url' => 'https://mitsoftware.com/', 'page_type' => 'home', 'markdown' => 'Servicios de desarrollo en Laravel para freelancers y autónomos. Trabajamos con freelancers y colaboradores externos. Soporte 24/7. © 2026.'],
        ['url' => 'https://mitsoftware.com/servicios', 'page_type' => 'services', 'markdown' => 'Desarrollo Laravel, Vue y APIs. Planes de horas para freelancer. latitude: 2 developers.'],
    ]);

    expect($result['is_natural_person'])->toBeFalse();
});

it('still flags a sole trader proven by identification data', function (): void {
    $byLegalPage = app(PublicCompanyDataExtractor::class)->extract([
        ['url' => 'https://juan.example/', 'page_type' => 'home', 'markdown' => 'Desarrollo Laravel freelance.'],
        ['url' => 'https://juan.example/aviso-legal', 'page_type' => 'legal', 'markdown' => "Juan Pérez Pérez, autónomo\nNIF 12345678Z"],
    ]);

    $bySelfDescription = app(PublicCompanyDataExtractor::class)->extract([
        ['url' => 'https://solo.example/', 'page_type' => 'home', 'markdown' => 'Hola, soy Juan Pérez, desarrollador Laravel freelance. Este es mi portfolio personal.'],
    ]);

    expect($byLegalPage['is_natural_person'])->toBeTrue()
        ->and($bySelfDescription['is_natural_person'])->toBeTrue();
});

it('does not read a marketplace as a sole trader', function (): void {
    // "Soy cliente, busco agencias" + the word freelance elsewhere on a
    // directory page must never be a discard signal.
    $result = app(PublicCompanyDataExtractor::class)->extract([
        ['url' => 'https://directorio.example/', 'page_type' => 'home', 'markdown' => 'Únase como proveedor o cliente. Soy cliente, busco agencias. Directors: Freelance Laravel, PHP, Vue. Con miles de freelancers verificados.'],
    ]);

    expect($result['is_natural_person'])->toBeFalse();
});

it('flags natural persons and never stores phones or street addresses', function (): void {
    $result = app(PublicCompanyDataExtractor::class)->extract([
        ['url' => 'https://juan.example/aviso', 'page_type' => 'legal', 'markdown' => "Juan Pérez, autónomo\nNIF 12345678Z\nCalle Mayor 1, 28001 Madrid\nTel: +34 910 111 222"],
    ]);

    $flat = json_encode($result['data']);

    expect($result['is_natural_person'])->toBeTrue()
        ->and($flat)->not->toContain('+34 910 111 222', 'Calle Mayor');
});
