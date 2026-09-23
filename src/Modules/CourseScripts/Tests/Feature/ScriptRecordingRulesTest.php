<?php

declare(strict_types=1);

use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptClosingAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptOutlineAgent;
use Modules\CourseScripts\Infrastructure\Ai\GenerateScriptSectionAgent;
use Modules\CourseScripts\Infrastructure\Ai\ReviewScriptAgent;
use Modules\CourseScripts\Infrastructure\Ai\ScriptFormatGuide;

/**
 * The recording rules of the supervisor-approved video 28 re-recording must
 * reach every writer and the reviewer; a prompt refactor that drops them
 * silently brings back read-out prompts and mid-sentence cuts.
 */
it('gives every script agent the approved recording rules', function (string $agent): void {
    $instructions = (string) app($agent)->instructions();

    expect($instructions)->toContain('RECORDING RULES')
        ->toContain('BRIDGE SENTENCE')
        ->toContain('[CORTE]')
        ->toContain('[SIN CORTE]');
})->with([
    GenerateScriptOutlineAgent::class,
    GenerateScriptSectionAgent::class,
    GenerateScriptClosingAgent::class,
    ReviewScriptAgent::class,
]);

it('orders every prompt as bridge, paste, narration while generating and a brief result explanation', function (): void {
    expect((string) app(GenerateScriptSectionAgent::class)->instructions())
        ->toContain('narration said while the tool')
        ->toContain('without reading every');
});

it('puts the editing rules into the recording notes and the final checklist', function (): void {
    expect((string) app(GenerateScriptClosingAgent::class)->instructions())
        ->toContain('repeat the WHOLE sentence')
        ->toContain('150 ms of air')
        ->toContain('no clipped word');
});

it('makes the reviewer fail drafts that break the recording rules', function (): void {
    expect((string) app(ReviewScriptAgent::class)->instructions())->toContain('scores at most 5');
});

it('keeps the recording rules inside the shared script guide', function (): void {
    expect(ScriptFormatGuide::SCRIPT)->toContain('150–160 spoken words per')
        ->toContain('never read aloud');
});
