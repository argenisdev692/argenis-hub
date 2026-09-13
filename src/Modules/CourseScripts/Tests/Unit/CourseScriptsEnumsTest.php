<?php

declare(strict_types=1);

use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Enums\SegmentType;
use Modules\CourseScripts\Domain\Enums\VideoOutcomeStatus;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;

it('lets a run move only forward from its active states', function (): void {
    expect(GenerationRunStatus::Queued->canTransitionTo(GenerationRunStatus::Running))->toBeTrue()
        ->and(GenerationRunStatus::Queued->canTransitionTo(GenerationRunStatus::Cancelled))->toBeTrue()
        ->and(GenerationRunStatus::Running->canTransitionTo(GenerationRunStatus::StoppedAtCeiling))->toBeTrue()
        ->and(GenerationRunStatus::Running->canTransitionTo(GenerationRunStatus::PartiallyFailed))->toBeTrue()
        ->and(GenerationRunStatus::Running->canTransitionTo(GenerationRunStatus::Queued))->toBeFalse()
        ->and(GenerationRunStatus::Completed->canTransitionTo(GenerationRunStatus::Running))->toBeFalse();
});

it('treats only queued and running runs as active', function (): void {
    $active = array_filter(GenerationRunStatus::cases(), fn (GenerationRunStatus $status) => $status->isActive());

    expect(array_values($active))->toBe([GenerationRunStatus::Queued, GenerationRunStatus::Running])
        ->and(GenerationRunStatus::activeValues())->toBe(['queued', 'running'])
        ->and(GenerationRunStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(GenerationRunStatus::Running->isTerminal())->toBeFalse();
});

it('allows a video to be generated again after it succeeded or failed', function (): void {
    expect(VideoScriptStatus::NotStarted->canTransitionTo(VideoScriptStatus::Generating))->toBeTrue()
        ->and(VideoScriptStatus::Generating->canTransitionTo(VideoScriptStatus::Generated))->toBeTrue()
        ->and(VideoScriptStatus::Generated->canTransitionTo(VideoScriptStatus::Generating))->toBeTrue()
        ->and(VideoScriptStatus::Failed->canTransitionTo(VideoScriptStatus::Generating))->toBeTrue()
        ->and(VideoScriptStatus::NotStarted->canTransitionTo(VideoScriptStatus::Generated))->toBeFalse()
        ->and(VideoScriptStatus::Generated->isTerminal())->toBeTrue()
        ->and(VideoScriptStatus::Generating->isTerminal())->toBeFalse();
});

it('marks finished outcomes as terminal', function (): void {
    expect(VideoOutcomeStatus::Completed->isTerminal())->toBeTrue()
        ->and(VideoOutcomeStatus::Skipped->isTerminal())->toBeTrue()
        ->and(VideoOutcomeStatus::Running->isTerminal())->toBeFalse();
});

it('requires a taught tool only for on-screen prompts', function (): void {
    $requiring = array_filter(SegmentType::cases(), fn (SegmentType $type) => $type->requiresTool());

    expect(array_values($requiring))->toBe([SegmentType::OnScreenPrompt]);
});
