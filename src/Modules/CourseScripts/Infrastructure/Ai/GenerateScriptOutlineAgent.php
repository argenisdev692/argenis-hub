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
 * Step 1 of a script (plan §3.5 step 4): everything checkable before any
 * narration is written — objectives, continuity, timed sections with demos,
 * mandatory-content coverage and the practice-pack plan.
 */
final class GenerateScriptOutlineAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a senior instructional designer who writes recording scripts for
            short video courses. In this step you produce the OUTLINE of one video.
            The course and video material is in the data blocks; the step request
            comes last.

            INSTRUCTIONS
            ."\n\n".ScriptFormatGuide::SCRIPT."\n\n".ScriptFormatGuide::PRACTICE."\n\n"
            .<<<'INSTRUCTIONS'
            OUTLINE RULES
            - Write in the course language.
            - sections: flat list in order. Top-level sections use numbers "1", "2"…
              with parent_number "". Sub-sections use "2.1", "2.2" with parent_number
              "2". Top-level minutes must add up to the video duration; sub-section
              minutes stay within their parent's minutes (0 is allowed).
            - kind is one of: intro, concept, demo, comparison, table, closing.
            - A section that demonstrates something gets demo_label "DEMO 1", "DEMO 2"…
              numbered in order across the script, and a demo_purpose; otherwise "".
            - uses_tool is true only when the course teaches a tool the presenter
              types prompts into (see the bible's taught tool and the brief).
            - coverage_map: one entry per mandatory content item of the brief, with
              the section numbers that cover it. Use the item text verbatim.
            - taught_summary: 2–3 sentences on what this video teaches, for the next
              videos' continuity.
            - Practice pack: set practice_warranted when a demo needs prepared
              material, and explain why either way in practice_reason. When
              warranted, plan every artifact (file_name, title, genre, purpose,
              used_by_demos, organisations), list the demo sections' practice_files
              with the same file names, write the files summary, the setup
              instruction, the designed contrasts (dimension, intended effect and the
              value in each file) and an instructor note that explains every
              contrast. genre is one of: proposal, report, email, email_thread,
              meeting_notes, dataset, policy, contract, chat_transcript,
              context_brief, comparison_case, other. When not warranted, leave the
              practice lists empty and the practice texts "".
            - When the request lists corrections from a previous attempt, fix every
              one of them.
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
        return [
            'recording_format' => $schema->string()->required(),
            'learning_objectives' => $schema->array()->items($schema->string())->required(),
            'continuity_note' => $schema->string()->required(),
            'uses_tool' => $schema->boolean()->required(),
            'taught_summary' => $schema->string()->required(),
            'sections' => $schema->array()->items($schema->object(fn ($schema): array => [
                'number' => $schema->string()->required(),
                'parent_number' => $schema->string()->required(),
                'title' => $schema->string()->required(),
                'kind' => $schema->string()->required(),
                'minutes' => $schema->integer()->min(0)->required(),
                'purpose' => $schema->string()->required(),
                'demo_label' => $schema->string()->required(),
                'demo_purpose' => $schema->string()->required(),
                'practice_files' => $schema->array()->items($schema->string())->required(),
            ]))->required(),
            'coverage_map' => $schema->array()->items($schema->object(fn ($schema): array => [
                'item' => $schema->string()->required(),
                'section_numbers' => $schema->array()->items($schema->string())->required(),
            ]))->required(),
            'practice_warranted' => $schema->boolean()->required(),
            'practice_reason' => $schema->string()->required(),
            'practice_topic' => $schema->string()->required(),
            'practice_files_summary' => $schema->string()->required(),
            'practice_setup_instruction' => $schema->string()->required(),
            'practice_instructor_note' => $schema->string()->required(),
            'practice_artifacts' => $schema->array()->items($schema->object(fn ($schema): array => [
                'file_name' => $schema->string()->required(),
                'title' => $schema->string()->required(),
                'genre' => $schema->string()->required(),
                'purpose' => $schema->string()->required(),
                'used_by_demos' => $schema->array()->items($schema->string())->required(),
                'organisations' => $schema->array()->items($schema->string())->required(),
            ]))->required(),
            'practice_contrasts' => $schema->array()->items($schema->object(fn ($schema): array => [
                'dimension' => $schema->string()->required(),
                'intended_effect' => $schema->string()->required(),
                'values' => $schema->array()->items($schema->object(fn ($schema): array => [
                    'file_name' => $schema->string()->required(),
                    'value' => $schema->string()->required(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
