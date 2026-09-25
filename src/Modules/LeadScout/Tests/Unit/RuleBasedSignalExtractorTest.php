<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Services\RuleBasedSignalExtractor;

function extractor(): RuleBasedSignalExtractor
{
    return app(RuleBasedSignalExtractor::class);
}

function pages(string $markdown, string $url = 'https://agencia.example/servicios'): array
{
    return [['url' => $url, 'markdown' => $markdown, 'fetched_at' => '2026-09-17 10:00:00']];
}

function posting(array $overrides = []): array
{
    return [
        'title' => 'Desarrollador Laravel freelance remoto',
        'body' => 'Buscamos Laravel con Vue.',
        'remote_mode' => 'remote',
        'contract_type' => 'freelance',
        'language' => 'es',
        'published_at' => '2026-09-10 09:00:00',
        'status' => 'active',
        'source_url' => 'https://ofertas.example.com/1',
        ...$overrides,
    ];
}

function keysOf(array $signals): array
{
    return array_column($signals, 'signal_key');
}

it('extracts tech, freelance, vacancy and team signals', function (): void {
    $signals = extractor()->extract(
        ['country' => 'ES', 'canonical_domain' => 'agencia.example'],
        pages('Desarrollamos con Laravel y Vue, PostgreSQL y Redis. Somos 12 personas. © 2026. Caso de éxito del 12 de mayo de 2026.'),
        [posting()],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => CarbonImmutable::parse('2026-09-17')],
    );

    expect(keysOf($signals))->toContain(
        'laravel', 'vue_inertia', 'stack_db', 'freelance_contract', 'active_vacancy',
        'vacancy_vitality', 'recent_content', 'copyright_recent', 'team_5_50',
        'lang_es_pt', 'country_pt_es', 'remote',
    );
});

it('flags freelancer, dead and inactive webs', function (): void {
    $now = CarbonImmutable::parse('2026-09-17');

    $solo = extractor()->extract(
        ['country' => 'ES', 'canonical_domain' => 'juan.example'],
        pages('Hola, soy Juan, desarrollador Laravel freelance. Este es mi portfolio personal.'),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => $now],
    );
    $dead = extractor()->extract(
        ['country' => 'ES', 'canonical_domain' => 'vieja.example'],
        pages('This domain is for sale. Buy this domain today.'),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => $now],
    );
    $stale = extractor()->extract(
        ['country' => 'PT', 'canonical_domain' => 'parada.example'],
        pages('Último caso: 10 de maio de 2021. © 2022.'),
        [],
        ['sitemap_lastmod' => '2022-03-01', 'final_domain' => null, 'now' => $now],
    );

    expect(keysOf($solo))->toContain('solo_freelancer')
        ->and(keysOf($dead))->toContain('dead_web')
        ->and(keysOf($stale))->toContain('stale_content', 'old_copyright', 'old_sitemap');
});

it('grades english cues without discarding', function (): void {
    $now = CarbonImmutable::parse('2026-09-17');
    $company = ['country' => 'NL', 'canonical_domain' => 'agency.example'];

    $async = extractor()->extract(
        $company,
        pages('We are a remote-first agency with a distributed team. Async by default, written communication.'),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => $now],
    );
    $fluent = extractor()->extract(
        $company,
        pages('Senior developer. Daily client calls, native English required.'),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => $now],
    );

    expect(keysOf($async))->toContain('async_english')->not->toContain('english_fluent_required')
        ->and(keysOf($fluent))->toContain('english_fluent_required')->not->toContain('async_english');
});

it('detects ES working language from page wording without the word español', function (): void {
    $signals = extractor()->extract(
        ['country' => 'ES', 'canonical_domain' => 'agencia.example'],
        pages('Nosotros somos una agencia de desarrollo. Nuestros servicios incluyen Laravel y soporte. Empresa fundada en Madrid.'),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => CarbonImmutable::parse('2026-09-17')],
    );

    $lang = null;

    foreach ($signals as $signal) {
        if ($signal['signal_key'] === 'lang_es_pt') {
            $lang = $signal;
        }
    }

    // Fix comm-33: this page previously fell through to english_unknown × 0.6 = 33.
    expect($lang)->not->toBeNull()
        ->and($lang['evidence_url'])->not->toBeNull()
        ->and($lang['evidence_excerpt'])->not->toBeNull();
});

it('anchors every signal to evidence (FR-7: no null URL/excerpt)', function (): void {
    $signals = extractor()->extract(
        ['country' => 'ES', 'canonical_domain' => 'agencia.example'],
        pages('Desarrollamos con Laravel y Vue, PostgreSQL y Redis. Somos 12 personas. © 2026. Caso de éxito del 12 de mayo de 2026. Nuestros clientes nos recomiendan.'),
        [posting()],
        ['sitemap_lastmod' => '2026-08-01', 'final_domain' => null, 'now' => CarbonImmutable::parse('2026-09-17')],
    );

    expect($signals)->not->toBeEmpty();

    foreach ($signals as $signal) {
        expect($signal['evidence_url'])->not->toBeNull("{$signal['signal_key']} has no evidence_url")
            ->and($signal['evidence_excerpt'])->not->toBeNull("{$signal['signal_key']} has no evidence_excerpt");
    }
});

it('needs an invitation, not a market comparison, to fire freelance_contract', function (): void {
    $now = CarbonImmutable::parse('2026-09-17');
    $company = ['country' => 'ES', 'canonical_domain' => 'unimedia.tech'];

    // Regression 2026-09-25: unimedia.tech's own nearshore page COMPARES the
    // model with freelancers and says offshore "puede parecer más barato".
    // The bare nouns fired +35 and −25 on a page with no commercial offer.
    $comparison = extractor()->extract(
        $company,
        pages("¿En qué se diferencia del offshore o de trabajar con freelancers?\nLos freelancers encajan muy bien en proyectos concretos, pero pueden generar dependencia.\nEl offshore suele ofrecer tarifas más bajas. El offshore puede parecer más barato por hora."),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => $now],
    );

    expect(keysOf($comparison))->not->toContain('freelance_contract', 'low_prices');

    // A real invitation and a real price still fire.
    $invitation = extractor()->extract(
        $company,
        pages('Trabajamos con freelancers y colaboradores externos. Mantenimiento desde 300€ al mes. Somos una agencia de software.'),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => $now],
    );

    expect(keysOf($invitation))->toContain('freelance_contract', 'low_prices');
});

it('keeps every excerpt literal to its source text', function (): void {
    $signals = extractor()->extract(
        ['country' => 'ES', 'canonical_domain' => 'agencia.example'],
        pages('Texto previo. Trabajamos con freelancers y partners de marca blanca. Texto posterior.'),
        [],
        ['sitemap_lastmod' => null, 'final_domain' => null, 'now' => CarbonImmutable::parse('2026-09-17')],
    );

    $found = null;

    foreach ($signals as $signal) {
        if ($signal['signal_key'] === 'accepts_external') {
            $found = $signal;
        }
    }

    expect($found)->not->toBeNull()
        ->and(str_contains('Texto previo. Trabajamos con freelancers y partners de marca blanca. Texto posterior.', (string) $found['evidence_excerpt']))->toBeTrue();
});
