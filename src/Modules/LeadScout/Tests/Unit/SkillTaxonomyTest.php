<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\ValueObjects\SkillTaxonomy;

it('splits confirmed from potential skills', function (): void {
    $body = 'Senior Laravel developer with Vue, Inertia and PostgreSQL. PHPUnit tested.';

    $split = SkillTaxonomy::split($body, SkillTaxonomy::DEFAULT_WATCH_LIST);

    expect($split['confirmed'])->toContain('laravel', 'vue', 'inertia', 'postgresql', 'phpunit')
        ->and($split['confirmed'])->not->toContain('aws', 'pest', 'forge')
        ->and($split['potential'])->toContain('aws', 'pest', 'forge');
});

it('matches whole words only', function (): void {
    expect(SkillTaxonomy::mentions('Vue.js developer', 'js'))->toBeFalse()
        ->and(SkillTaxonomy::mentions('JavaScript and JS', 'js'))->toBeTrue()
        ->and(SkillTaxonomy::mentions('PostgreSQL and Supabase', 'postgresql'))->toBeTrue();
});

it('never reports an unmentioned technology as confirmed', function (): void {
    $split = SkillTaxonomy::split('PHP developer, MySQL and Docker.', ['aws']);

    expect($split['confirmed'])->not->toContain('aws')
        ->and($split['potential'])->toContain('aws');
});

it('lists claimed capabilities the profile has not confirmed', function (): void {
    $claims = SkillTaxonomy::unconfirmedClaims('Trabajo con Laravel y AWS cada día.', ['Laravel'], ['aws']);

    expect($claims)->toBe(['aws'])
        ->and(SkillTaxonomy::unconfirmedClaims('Trabajo con Laravel.', ['laravel'], ['aws']))->toBe([]);
});
