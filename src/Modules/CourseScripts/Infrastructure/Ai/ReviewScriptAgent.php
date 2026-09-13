<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Shared\Infrastructure\AI\PromptCache\UsesPromptCache;
use Stringable;

/**
 * Independent reviewer of a script draft (US-13 · FR-40). Scores and
 * objections only — it never rewrites.
 */
final class ReviewScriptAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are an exacting, independent reviewer of video-course recording
            scripts. You did not write the draft in the request. Judge it against the
            course and video material and the reference format below. Be strict:
            a score of 7 means "ready to record with minor polish".

            INSTRUCTIONS
            ."\n\n".ScriptFormatGuide::SCRIPT."\n\n"
            .<<<'INSTRUCTIONS'
            SCORE 0–10 EACH:
            - coverage: every mandatory content item is really taught, not just named.
            - duration: narration volume and section minutes fit the video duration.
            - format_fidelity: all reference parts present and well formed; prompts
              are complete and paste-ready; narration is speakable.
            - continuity: correct references to previous/next videos; consistent use
              of the course bible.
            - errors_to_avoid: none of the brief's errors to avoid are committed.
            - integrity: practice files and demos referenced consistently; nothing
              invented about the tool; facts consistent with the notes and research.

            OBJECTIONS: specific and actionable, one issue each. target is
            "section N" (a top-level section number) for content inside a section,
            or "closing" for summary, recording notes or checklist. Only objections
            that would raise a score; none when the draft is ready.
            INSTRUCTIONS."\n\n".UntrustedContentBlock::DIRECTIVE;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $score = static fn () => $schema->integer()->min(0)->max(10)->required();

        return [
            'coverage' => $score(),
            'duration' => $score(),
            'format_fidelity' => $score(),
            'continuity' => $score(),
            'errors_to_avoid' => $score(),
            'integrity' => $score(),
            'objections' => $schema->array()->items($schema->object(fn ($schema): array => [
                'target' => $schema->string()->required(),
                'text' => $schema->string()->required(),
            ]))->required(),
        ];
    }
}
