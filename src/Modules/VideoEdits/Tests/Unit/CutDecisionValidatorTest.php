<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;
use Modules\VideoEdits\Domain\Services\CutDecisionValidator;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;

function decisionBetween(int $startMs, int $endMs, ?float $confidence = null): CutDecision
{
    return new CutDecision('manual', CutReason::Manual, DecisionOrigin::User, $startMs, $endMs, $confidence);
}

it('applies decisions that fit inside the media, including the very last millisecond', function (): void {
    $validated = (new CutDecisionValidator)->validate([
        decisionBetween(0, 1_000),
        decisionBetween(59_000, 60_000, 0.9),
    ], 60_000);

    expect($validated)->toHaveCount(2)
        ->and($validated[0]->outcome)->toBe(DecisionOutcome::Applied)
        ->and($validated[1]->outcome)->toBe(DecisionOutcome::Applied)
        ->and($validated[1]->rejectionReason)->toBeNull();
});

it('rejects each invalid decision with its reason', function (CutDecision $decision, DecisionRejectionReason $reason): void {
    [$validated] = (new CutDecisionValidator)->validate([$decision], 60_000);

    expect($validated->outcome)->toBe(DecisionOutcome::Rejected)
        ->and($validated->rejectionReason)->toBe($reason)
        ->and($validated->decision)->toBe($decision);
})->with([
    'negative start' => [fn () => decisionBetween(-10, 500), DecisionRejectionReason::NegativeStart],
    'start equals end' => [fn () => decisionBetween(2_000, 2_000), DecisionRejectionReason::StartNotBeforeEnd],
    'reversed' => [fn () => decisionBetween(3_000, 2_000), DecisionRejectionReason::StartNotBeforeEnd],
    'beyond the media' => [fn () => decisionBetween(59_500, 60_001), DecisionRejectionReason::EndBeyondDuration],
    'confidence above 1' => [fn () => decisionBetween(0, 500, 1.2), DecisionRejectionReason::ConfidenceOutOfRange],
    'confidence below 0' => [fn () => decisionBetween(0, 500, -0.1), DecisionRejectionReason::ConfidenceOutOfRange],
]);

it('keeps valid and invalid decisions side by side instead of failing the whole set', function (): void {
    $validated = (new CutDecisionValidator)->validate([
        decisionBetween(0, 1_000),
        decisionBetween(90_000, 91_000),
        decisionBetween(5_000, 6_000),
    ], 60_000);

    expect(array_map(static fn ($v) => $v->outcome, $validated))->toBe([
        DecisionOutcome::Applied,
        DecisionOutcome::Rejected,
        DecisionOutcome::Applied,
    ]);
});
