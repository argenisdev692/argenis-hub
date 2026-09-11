<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\VideoEditMode;

it('keeps AI edit on the roadmap but unavailable in V1', function (): void {
    expect(VideoEditMode::AiEdit->isAvailable())->toBeFalse()
        ->and(VideoEditMode::availableValues())->toBe(['merge', 'auto_edit']);
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
