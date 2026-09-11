<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\ProcessingStage;

it('splits the progress bar into weights that add up to 100', function (): void {
    $total = array_sum(array_map(
        static fn (ProcessingStage $stage): int => $stage->weight(),
        ProcessingStage::cases(),
    ));

    expect($total)->toBe(100);
});

it('starts each stage where the previous one ended', function (ProcessingStage $stage, int $start): void {
    expect($stage->startPercent())->toBe($start);
})->with([
    'download' => [ProcessingStage::Download, 0],
    'merge' => [ProcessingStage::Merge, 5],
    'analysis' => [ProcessingStage::Analysis, 20],
    'plan cuts' => [ProcessingStage::PlanCuts, 30],
    'render' => [ProcessingStage::Render, 35],
    'publish' => [ProcessingStage::Publish, 95],
]);
