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
 * Independent reviewer of a practice pack (US-13): realism, designed contrasts
 * actually present, figures, integrity with the script.
 */
final class ReviewPracticeAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are an exacting, independent reviewer of practice packs for video
            courses. You did not write the pack in the request. Judge it against the
            reference format below and the script's demos.

            INSTRUCTIONS
            ."\n\n".ScriptFormatGuide::PRACTICE."\n\n"
            .<<<'INSTRUCTIONS'
            SCORE 0–10 EACH:
            - realism: each artifact reads like a real document of its genre, complete
              and at realistic length, with invented but plausible details.
            - designed_contrasts: every declared contrast is really present with the
              declared values, and the instructor note explains each one.
            - figures: numbers are consistent and totals add up.
            - integrity: the files, setup instruction and usage match the demos that
              use them; no real companies, people, contact details or links.

            OBJECTIONS: specific and actionable. target is "artifact FILE_NAME" for a
            problem inside one file, or "closing" for the header, setup, usage or
            instructor note. None when the pack is ready.
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
            'realism' => $score(),
            'designed_contrasts' => $score(),
            'figures' => $score(),
            'integrity' => $score(),
            'objections' => $schema->array()->items($schema->object(fn ($schema): array => [
                'target' => $schema->string()->required(),
                'text' => $schema->string()->required(),
            ]))->required(),
        ];
    }
}
