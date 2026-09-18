<?php

declare(strict_types=1);

use Modules\LeadScout\Domain\Enums\DiscardReason;
use Modules\LeadScout\Domain\Enums\Tier;
use Modules\LeadScout\Domain\Services\TierClassifier;

function thresholds(): array
{
    return [
        'a_min_score' => 80, 'a_min_confidence' => 70,
        'b_min_score' => 65, 'b_min_confidence' => 50,
        'needs_research' => [
            'high_score_low_confidence' => ['score' => 80, 'confidence_below' => 70],
            'unknown_activity_min_score' => 65,
        ],
    ];
}

function flags(array $overrides = []): array
{
    return [
        'solo_freelancer' => false, 'dead_or_absorbed' => false, 'inactive_agency' => false,
        'outsourcer_large' => false, 'remote_zero' => false, ...$overrides,
    ];
}

function classifyA(int $score = 87, int $confidence = 78, array $override = []): array
{
    return app(TierClassifier::class)->classify(
        $score, $confidence, 80, 'active', 'from_11_to_50', 25,
        ['freelance_contract', 'agency_type', 'active_vacancy'],
        flags($override['flags'] ?? []),
        $override['country'] ?? 'ES',
        thresholds(),
    );
}

it('promotes a qualified agency to A', function (): void {
    $result = classifyA();

    expect($result['tier'])->toBe(Tier::A)
        ->and($result['discardReason'])->toBeNull()
        ->and($result['needsResearch'])->toBeFalse();
});

it('holds the 80/70 and 65/50 borders', function (): void {
    expect(classifyA(79)['tier'])->toBe(Tier::B)
        ->and(classifyA(87, 69)['tier'])->toBe(Tier::B)
        ->and(classifyA(87, 69)['needsResearch'])->toBeTrue()
        ->and(classifyA(64)['tier'])->toBe(Tier::C);
});

it('never reaches A without a commercial fact', function (): void {
    $result = app(TierClassifier::class)->classify(
        90, 90, 80, 'active', 'from_11_to_50', 25, ['laravel'], flags(), 'ES', thresholds(),
    );

    expect($result['tier'])->toBe(Tier::B);
});

it('caps 2-4 person teams at B without a buying signal', function (): void {
    $without = app(TierClassifier::class)->classify(
        85, 80, 80, 'active', 'from_2_to_4', 3, ['freelance_contract'], flags(), 'ES', thresholds(),
    );
    $withVacancy = app(TierClassifier::class)->classify(
        85, 80, 80, 'active', 'from_2_to_4', 3, ['freelance_contract', 'active_vacancy'], flags(), 'ES', thresholds(),
    );

    expect($without['tier'])->toBe(Tier::B)
        ->and($withVacancy['tier'])->toBe(Tier::A);
});

it('discards with reasons and never confuses them', function (): void {
    expect(classifyA(override: ['flags' => ['solo_freelancer' => true]])['discardReason'])->toBe(DiscardReason::SoloFreelancer)
        ->and(classifyA(override: ['flags' => ['dead_or_absorbed' => true]])['discardReason'])->toBe(DiscardReason::DeadOrAcquired)
        ->and(classifyA(override: ['flags' => ['inactive_agency' => true]])['discardReason'])->toBe(DiscardReason::Inactive)
        ->and(classifyA(override: ['flags' => ['outsourcer_large' => true]])['discardReason'])->toBe(DiscardReason::LargeOutsourcer)
        ->and(classifyA(override: ['flags' => ['remote_zero' => true], 'country' => 'NL'])['discardReason'])->toBe(DiscardReason::OnsiteAbroad);
});

it('discards low technical scores and keeps remote-zero PT leads', function (): void {
    $low = app(TierClassifier::class)->classify(
        50, 80, 20, 'active', 'from_11_to_50', 25, ['freelance_contract'], flags(), 'ES', thresholds(),
    );
    $ptOnsite = app(TierClassifier::class)->classify(
        70, 60, 70, 'active', 'from_11_to_50', 25, ['freelance_contract'], flags(['remote_zero' => true]), 'PT', thresholds(),
    );

    expect($low['discardReason'])->toBe(DiscardReason::LowTechnical)
        ->and($ptOnsite['tier'])->toBe(Tier::B);
});

it('marks unknown-activity leads for research instead of discarding', function (): void {
    $result = app(TierClassifier::class)->classify(
        70, 60, 70, 'unknown', 'unknown', null, ['freelance_contract'], flags(), 'ES', thresholds(),
    );

    expect($result['tier'])->toBe(Tier::B)
        ->and($result['needsResearch'])->toBeTrue();
});
