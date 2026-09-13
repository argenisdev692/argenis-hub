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
 * Step 4 (plan §3.5 step 8, R12.2): the full content of ONE practice artifact,
 * one call per file so a pack of several long documents never crowds a single
 * structured response.
 */
final class GeneratePracticeArtifactAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You write ONE complete simulated document for the practice pack of a video
            course. The practice plan, the demo that uses the document and the
            designed contrasts are in the request.

            INSTRUCTIONS
            ."\n\n".ScriptFormatGuide::PRACTICE."\n\n"
            .<<<'INSTRUCTIONS'
            ARTIFACT RULES
            - content_blocks in reading order. type is one of: heading (level 1–3,
              text), paragraph (text), list (items), table (table_header, table_rows,
              total_row true when the last row is a total), key_values (pairs),
              footer (text). Unused fields are "", 0, false or empty lists.
            - Write the complete document of its genre at realistic length, not a
              summary. Embody every designed contrast with the exact value the plan
              gives this file.
            - When the plan has sibling files that are compared, use the same section
              structure and table columns as they would, differing only where designed.
            - Totals must equal the sum of their rows. Use the course's number format.
            - Invented organisations and people only; invented email domains built
              from the invented company name; no URLs.
            - organisations and characters: every organisation and person that appears.
            - Write in the course language. Fix every correction listed in the request.
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
            'content_blocks' => $schema->array()->items($schema->object(fn ($schema): array => [
                'type' => $schema->string()->required(),
                'level' => $schema->integer()->min(0)->max(3)->required(),
                'text' => $schema->string()->required(),
                'items' => $schema->array()->items($schema->string())->required(),
                'table_header' => $schema->array()->items($schema->string())->required(),
                'table_rows' => $schema->array()->items($schema->array()->items($schema->string()))->required(),
                'total_row' => $schema->boolean()->required(),
                'pairs' => $schema->array()->items($schema->object(fn ($schema): array => [
                    'key' => $schema->string()->required(),
                    'value' => $schema->string()->required(),
                ]))->required(),
            ]))->required(),
            'organisations' => $schema->array()->items($schema->object(fn ($schema): array => [
                'name' => $schema->string()->required(),
                'role' => $schema->string()->required(),
                'sector' => $schema->string()->required(),
            ]))->required(),
            'characters' => $schema->array()->items($schema->object(fn ($schema): array => [
                'name' => $schema->string()->required(),
                'role' => $schema->string()->required(),
                'organisation' => $schema->string()->required(),
            ]))->required(),
        ];
    }
}
