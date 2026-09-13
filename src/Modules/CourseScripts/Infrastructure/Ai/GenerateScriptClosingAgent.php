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
 * Step 3 (plan §3.5 step 7, R11.4): the closing parts, written once every
 * section exists so the checklist can name what actually happens on screen.
 */
final class GenerateScriptClosingAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You write the CLOSING PARTS of a video script whose sections are already
            written (they are in the request).

            INSTRUCTIONS
            ."\n\n".ScriptFormatGuide::SCRIPT."\n\n"
            .<<<'INSTRUCTIONS'
            CLOSING RULES
            - summary_points: 5–8 bullet takeaways of what the video actually showed.
            - next_video_handoff: one sentence pointing to the next video by number
              and title, only when the material says there is a next video; "" when
              this is the last video.
            - preparation: everything to have ready before recording, naming every
              practice file exactly as declared.
            - during_recording: timing and emphasis cues tied to section numbers.
            - tools_required: tools, apps or connectors the recording needs; when none
              are needed, leave it empty and explain in tools_none_reason.
            - continuity: how this video connects with the previous and next ones.
            - organisations_used: the fictional organisations that appear. Only names
              from the course bible or from the practice pack.
            - verification_checklist: what must visibly happen on screen, one item per
              demonstration, plus format checks.
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
            'summary_points' => $schema->array()->items($schema->string())->required(),
            'next_video_handoff' => $schema->string()->required(),
            'preparation' => $schema->array()->items($schema->string())->required(),
            'during_recording' => $schema->array()->items($schema->string())->required(),
            'tools_required' => $schema->array()->items($schema->string())->required(),
            'tools_none_reason' => $schema->string()->required(),
            'continuity' => $schema->string()->required(),
            'organisations_used' => $schema->array()->items($schema->string())->required(),
            'verification_checklist' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
