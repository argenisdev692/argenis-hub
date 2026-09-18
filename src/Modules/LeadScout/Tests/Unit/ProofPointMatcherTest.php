<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Services\ProofPointMatcher;

function proofPoints(): array
{
    return [
        ['title' => 'Servispin', 'summary' => 'Booking platform', 'technologies' => ['laravel', 'vue'], 'sector' => 'hospitality', 'result' => '2x bookings', 'url' => 'https://servispin.example', 'order' => 1],
        ['title' => 'AquaShield', 'summary' => 'Performance landing', 'technologies' => ['laravel', 'livewire'], 'sector' => 'marketing', 'result' => '95+ Lighthouse', 'url' => 'https://aquashield.example', 'order' => 2],
        ['title' => 'Vidula', 'summary' => 'Modular SaaS', 'technologies' => ['laravel', 'vue', 'inertia'], 'sector' => 'saas', 'result' => '40% faster onboarding', 'url' => 'https://vidula.example', 'order' => 3],
    ];
}

it('picks the proof point with the largest technology overlap', function (): void {
    $match = app(ProofPointMatcher::class)->match(proofPoints(), ['laravel', 'vue', 'inertia'], null);

    expect($match['title'])->toBe('Vidula');
});

it('prefers the sector match on ties and recency after that', function (): void {
    $matcher = app(ProofPointMatcher::class);

    expect($matcher->match(proofPoints(), ['laravel', 'vue'], 'hospitality')['title'])->toBe('Servispin')
        ->and($matcher->match(proofPoints(), ['laravel'], null)['title'])->toBe('Vidula');
});

it('returns null without proof points', function (): void {
    expect(app(ProofPointMatcher::class)->match([], ['laravel'], null))->toBeNull();
});
