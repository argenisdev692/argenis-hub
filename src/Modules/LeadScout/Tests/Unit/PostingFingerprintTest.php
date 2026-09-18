<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\ValueObjects\PostingFingerprint;

it('is stable for the same posting within one ISO week', function (): void {
    $monday = CarbonImmutable::parse('2026-09-14');
    $sunday = CarbonImmutable::parse('2026-09-20');

    $a = PostingFingerprint::make('Acme Labs', 'Desarrollador Laravel', 'Madrid', $monday);
    $b = PostingFingerprint::make('  ACME labs ', 'desarrollador  laravel', 'Madrid', $sunday);

    expect($a->value)->toBe($b->value);
});

it('changes across ISO weeks, companies, titles and locations', function (): void {
    $date = CarbonImmutable::parse('2026-09-14');
    $base = PostingFingerprint::make('Acme', 'Dev Laravel', 'Madrid', $date);

    expect(PostingFingerprint::make('Acme', 'Dev Laravel', 'Madrid', $date->addWeek())->value)
        ->not->toBe($base->value)
        ->and(PostingFingerprint::make('Other', 'Dev Laravel', 'Madrid', $date)->value)
        ->not->toBe($base->value)
        ->and(PostingFingerprint::make('Acme', 'Dev Vue', 'Madrid', $date)->value)
        ->not->toBe($base->value)
        ->and(PostingFingerprint::make('Acme', 'Dev Laravel', 'Lisboa', $date)->value)
        ->not->toBe($base->value);
});
