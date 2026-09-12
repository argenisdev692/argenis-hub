<?php

declare(strict_types=1);

use Modules\VideoEdits\Application\Pipeline\ProgressReporter;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;

/**
 * @param  list<array{0: int, 1: ProcessingStage}>  $writes
 */
function progressReporter(array &$writes, float &$now): ProgressReporter
{
    $repository = Mockery::mock(VideoEditRepositoryPort::class);
    $repository->shouldReceive('updateProgress')->andReturnUsing(
        function (string $uuid, int $percent, ProcessingStage $stage) use (&$writes): void {
            $writes[] = [$percent, $stage];
        },
    );

    return new ProgressReporter($repository, 'edit-uuid', 2, 5.0, static function () use (&$now): float {
        return $now;
    });
}

it('always records stage starts and the final 100 percent', function (): void {
    $writes = [];
    $now = 0.0;
    $reporter = progressReporter($writes, $now);

    $reporter->startStage(ProcessingStage::Download);
    $reporter->startStage(ProcessingStage::Merge);
    $reporter->finish();

    expect($writes)->toBe([
        [0, ProcessingStage::Download],
        [5, ProcessingStage::Merge],
        [100, ProcessingStage::Publish],
    ]);
});

it('maps in-stage progress onto the overall bar', function (): void {
    $writes = [];
    $now = 0.0;
    $reporter = progressReporter($writes, $now);

    $reporter->startStage(ProcessingStage::Render);
    $now = 10.0;
    $reporter->advanceStage(ProcessingStage::Render, 50);

    // Derived, not hard-coded: the weight table is owned by
    // ProcessingStageTest and is rebalanced whenever a version adds stages.
    $base = ProcessingStage::Render->startPercent();

    expect($writes)->toBe([
        [$base, ProcessingStage::Render],
        [$base + intdiv(ProcessingStage::Render->weight(), 2), ProcessingStage::Render],
    ]);
});

it('throttles in-stage writes by step and by interval', function (): void {
    $writes = [];
    $now = 0.0;
    $reporter = progressReporter($writes, $now);

    $base = ProcessingStage::Render->startPercent();

    $reporter->startStage(ProcessingStage::Render);       // base
    $now = 1.0;
    $reporter->advanceStage(ProcessingStage::Render, 50); // too soon
    $now = 10.0;
    $reporter->advanceStage(ProcessingStage::Render, 1);  // step too small
    $reporter->advanceStage(ProcessingStage::Render, 10); // written

    expect(array_column($writes, 0))->toBe([
        $base,
        $base + intdiv(ProcessingStage::Render->weight() * 10, 100),
    ]);
});

it('never moves the bar backwards', function (): void {
    $writes = [];
    $now = 0.0;
    $reporter = progressReporter($writes, $now);

    $reporter->startStage(ProcessingStage::Render);
    $now = 10.0;
    $reporter->advanceStage(ProcessingStage::Render, 100);
    $reporter->startStage(ProcessingStage::PlanCuts);

    expect(array_column($writes, 0))->toBe([ProcessingStage::Render->startPercent(), 95, 95]);
});
