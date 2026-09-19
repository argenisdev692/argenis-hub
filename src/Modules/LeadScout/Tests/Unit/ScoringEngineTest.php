<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Services\ScoringEngine;

function signal(
    string $key,
    string $dimension,
    string $nature = 'fact',
    int $confidence = 80,
    ?string $url = 'https://agencia.example/pagina',
    int $daysAgo = 5,
): array {
    return [
        'signal_key' => $key,
        'dimension' => $dimension,
        'nature' => $nature,
        'confidence' => $confidence,
        'evidence_url' => $url,
        'captured_at' => CarbonImmutable::parse('2026-09-17')->subDays($daysAgo)->toDateTimeString(),
        'value_text' => null,
    ];
}

function sevillaSignals(): array
{
    $recent = signal('recent_content', 'vitality', 'fact', 90, null);
    $recent['value_text'] = 'Content from 2026-06-15';

    return [
        signal('laravel', 'technical', 'fact', 90),
        signal('vue_inertia', 'technical', 'fact', 85),
        signal('stack_db', 'technical'), signal('stack_db', 'technical'), signal('stack_db', 'technical'),
        signal('api_ai', 'technical', 'fact', 70),
        signal('freelance_contract', 'commercial'),
        signal('agency_type', 'commercial'),
        signal('multi_vacancies', 'commercial'),
        signal('accepts_external', 'commercial', 'inference', 80),
        signal('maintenance_sla', 'recurrent'),
        signal('long_term', 'recurrent', 'inference', 75),
        signal('many_cases', 'recurrent'),
        signal('active_vacancy', 'recurrent', 'fact', 90),
        $recent,
        signal('sitemap_fresh', 'vitality', 'fact', 75),
        signal('vacancy_vitality', 'vitality', 'fact', 90),
        signal('team_5_50', 'vitality'),
        signal('lang_es_pt', 'communication', 'fact', 95),
        signal('country_pt_es', 'geo_contract', 'fact', 95),
        signal('remote', 'remote', 'fact', 90),
    ];
}

function netherlandsSignals(): array
{
    $recent = signal('recent_content', 'vitality', 'fact', 85, null, 150);
    $recent['value_text'] = 'Content from 2026-04-15';

    return [
        signal('laravel', 'technical', 'fact', 90, 'https://agency.example/services'),
        signal('vue_inertia', 'technical', 'fact', 85, 'https://agency.example/services'),
        signal('stack_db', 'technical', 'fact', 75, 'https://agency.example/stack'),
        signal('stack_db', 'technical', 'fact', 75, 'https://agency.example/stack'),
        signal('stack_db', 'technical', 'fact', 75, 'https://agency.example/stack'),
        signal('api_ai', 'technical', 'fact', 70, 'https://partners.example.com/integrations'),
        signal('agency_type', 'commercial'),
        signal('accepts_external', 'commercial'),
        signal('maintenance_sla', 'recurrent'),
        signal('many_cases', 'recurrent'),
        signal('long_clients', 'recurrent', 'fact', 70),
        $recent,
        signal('sitemap_fresh', 'vitality', 'fact', 75),
        signal('team_5_50', 'vitality'),
        signal('async_english', 'communication'),
        signal('country_eu', 'geo_contract', 'fact', 90),
        signal('accepts_eu_contractors', 'geo_contract'),
        signal('remote', 'remote', 'fact', 90),
    ];
}

function scoringRules(): array
{
    return [
        'weights' => ['technical' => 20, 'commercial' => 20, 'recurrent' => 20, 'vitality' => 15, 'communication' => 10, 'geo_contract' => 10, 'remote' => 5],
        'inference_weight' => 0.6,
        'overlap_hours' => ['ES' => 8, 'NL' => 8],
    ];
}

it('scores the sevilla agency at 87 (plan §3.3 example 1)', function (): void {
    $result = app(ScoringEngine::class)->score(
        sevillaSignals(),
        ['laravel', 'php', 'vue', 'inertia', 'mysql', 'redis', 'docker'],
        ['laravel', 'vue'],
        scoringRules(),
        'ES',
        CarbonImmutable::parse('2026-09-17'),
    );

    expect($result['subscores'])->toMatchArray([
        'technical' => 80, 'commercial' => 96, 'recurrent' => 80, 'vitality' => 100,
        'communication' => 100, 'geo_contract' => 60, 'remote' => 100,
    ])->and($result['leadScore'])->toBe(87)
        ->and($result['confidence'])->toBeGreaterThanOrEqual(70)
        ->and($result['flags'])->toMatchArray(['solo_freelancer' => false, 'dead_or_absorbed' => false]);
});

it('scores the dutch agency at 75 (plan §3.3 example 2)', function (): void {
    $result = app(ScoringEngine::class)->score(
        netherlandsSignals(),
        ['laravel', 'php', 'vue', 'inertia'],
        ['laravel', 'vue'],
        scoringRules(),
        'NL',
        CarbonImmutable::parse('2026-09-17'),
    );

    expect($result['subscores'])->toMatchArray([
        'technical' => 80, 'commercial' => 60, 'recurrent' => 65, 'vitality' => 90,
        'communication' => 80, 'geo_contract' => 75, 'remote' => 100,
    ])->and($result['leadScore'])->toBe(75);
});

it('weighs inferences less than facts and penalizes unconfirmed tech', function (): void {
    $engine = app(ScoringEngine::class);

    $fact = $engine->score(
        [signal('laravel', 'technical')],
        ['laravel'], ['laravel'], scoringRules(), 'ES', CarbonImmutable::parse('2026-09-17'),
    );
    $inference = $engine->score(
        [signal('laravel', 'technical', 'inference')],
        ['laravel'], ['laravel'], scoringRules(), 'ES', CarbonImmutable::parse('2026-09-17'),
    );

    expect($fact['subscores']['technical'])->toBe(40)
        ->and($inference['subscores']['technical'])->toBe(24);

    $gap = $engine->score(
        [signal('laravel', 'technical')],
        ['laravel'], ['laravel', 'vue', 'aws', 'kubernetes', 'terraform'],
        scoringRules(), 'ES', CarbonImmutable::parse('2026-09-17'),
    );

    // 4 required-but-unconfirmed techs → −30 capped.
    expect($gap['subscores']['technical'])->toBe(10);
});

it('is deterministic for identical inputs', function (): void {
    $engine = app(ScoringEngine::class);
    $args = [sevillaSignals(), ['laravel'], ['laravel'], scoringRules(), 'ES', CarbonImmutable::parse('2026-09-17')];

    expect($engine->score(...$args))->toEqual($engine->score(...$args));
});
