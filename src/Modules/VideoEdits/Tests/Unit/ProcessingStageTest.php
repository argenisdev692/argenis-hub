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
    'analysis' => [ProcessingStage::Analysis, 17],
    // V2 stages follow analysis (EX-7): silence analysis really does run first.
    'audio extraction' => [ProcessingStage::AudioExtraction, 23],
    'transcription' => [ProcessingStage::Transcription, 26],
    'speech detection' => [ProcessingStage::SpeechDetection, 39],
    // V3 stages follow those: the AI reads the transcript they produce.
    'script extraction' => [ProcessingStage::ScriptExtraction, 43],
    'ai analysis' => [ProcessingStage::AiAnalysis, 45],
    'plan cuts' => [ProcessingStage::PlanCuts, 55],
    'render' => [ProcessingStage::Render, 57],
    'publish' => [ProcessingStage::Publish, 95],
]);

it('never lets a later stage start before an earlier one', function (): void {
    // The reporter clamps progress so it cannot move backwards; a stage table
    // out of execution order would silently relabel the bar without advancing
    // it, which is how a transcription ends up looking like a hung job.
    $starts = array_map(
        static fn (ProcessingStage $stage): int => $stage->startPercent(),
        ProcessingStage::cases(),
    );
    $sorted = $starts;
    sort($sorted);

    expect($starts)->toBe($sorted);
});
