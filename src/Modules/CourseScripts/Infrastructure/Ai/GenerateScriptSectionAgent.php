<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Shared\Infrastructure\AI\PromptCache\UsesPromptCache;
use Stringable;

/**
 * Step 2 (plan §3.5 step 6): the recordable content of one top-level section
 * and its sub-sections, as typed segments (FR-30).
 */
final class GenerateScriptSectionAgent implements Agent, Conversational, HasProviderOptions, HasStructuredOutput
{
    use Promptable;
    use RemembersConversations;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a senior instructional designer writing the recordable content of
            ONE section of a video script. The course material, the video material and
            the approved outline are in the data blocks; the request names the section.

            INSTRUCTIONS
            ."\n\n".ScriptFormatGuide::SCRIPT."\n\n"
            .<<<'INSTRUCTIONS'
            SECTION RULES
            - Return one entry in parts for the requested section and one for each of
              its sub-sections, in order, using the outline's section numbers.
            - Each part is a list of segments in recording order. type is one of:
              narration, on_screen_prompt, expected_result, on_screen_actions,
              show_on_screen, on_screen_table, presenter_note.
            - narration: text = the exact spoken words, natural and specific, sized to
              the section's minutes (150–160 spoken words per minute in total). Each
              narration is a continuous spoken paragraph with connectors, said in
              one breath — never a string of telegraphic one-liners, no pauses
              written in.
            - on_screen_prompt: prompt = the complete literal prompt to type. Only if
              the outline says the course uses a tool. The presenter pastes it and
              never reads it. Segment order around every prompt:
              narration (the bridge sentence: ONE sentence, at most ~15 words, what
              it asks for) → on_screen_prompt → narration said while the tool
              generates (what to notice) → expected_result → narration explaining
              the result quickly, only its important points, without reading every
              generated section → on_screen_actions → presenter_note whose items
              include one "[CORTE] …" naming where the generation wait is trimmed.
            - Any step that opens a new menu, panel, tab or scene gets its own
              on_screen_actions with every click, and a presenter_note item
              "[SIN CORTE] …": it is recorded whole, without cuts.
            - on_screen_actions and presenter_note: items = short imperative steps.
            - show_on_screen: text = what is shown; read_aloud true when the presenter
              reads it; practice_file = the practice file name when the material comes
              from the practice pack, otherwise "".
            - on_screen_table: table_columns and table_rows.
            - Unused fields are "" or empty lists.
            - Use the practice file names exactly as the outline declares them.
            - When the request lists corrections, fix every one of them.
            INSTRUCTIONS."\n\n".UntrustedContentBlock::DIRECTIVE;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'parts' => $schema->array()->items($schema->object(fn ($schema): array => [
                'section_number' => $schema->string()->required(),
                'segments' => $schema->array()->items($schema->object(fn ($schema): array => [
                    'type' => $schema->string()->required(),
                    'text' => $schema->string()->required(),
                    'prompt' => $schema->string()->required(),
                    'items' => $schema->array()->items($schema->string())->required(),
                    'table_columns' => $schema->array()->items($schema->string())->required(),
                    'table_rows' => $schema->array()->items($schema->array()->items($schema->string()))->required(),
                    'read_aloud' => $schema->boolean()->required(),
                    'practice_file' => $schema->string()->required(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
