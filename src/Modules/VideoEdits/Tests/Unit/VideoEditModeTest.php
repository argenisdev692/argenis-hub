<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\VideoEditMode;

it('offers all three modes now that V3 has shipped', function (): void {
    // AI edit existed as a mode from V1 so the roadmap was first-class in the
    // data model (EX-4); it became selectable when its producer shipped.
    expect(VideoEditMode::AiEdit->isAvailable())->toBeTrue()
        ->and(VideoEditMode::availableValues())->toBe(['merge', 'auto_edit', 'ai_edit']);
});

it('requires two clips to merge and one to auto edit', function (VideoEditMode $mode, int $minimum): void {
    expect($mode->minimumSources())->toBe($minimum);
})->with([
    'merge' => [VideoEditMode::Merge, 2],
    'auto edit' => [VideoEditMode::AutoEdit, 1],
    'ai edit' => [VideoEditMode::AiEdit, 1],
]);

it('accepts cut decisions in every mode except merge', function (): void {
    expect(VideoEditMode::Merge->acceptsCutDecisions())->toBeFalse()
        ->and(VideoEditMode::AutoEdit->acceptsCutDecisions())->toBeTrue()
        ->and(VideoEditMode::AiEdit->acceptsCutDecisions())->toBeTrue();
});
