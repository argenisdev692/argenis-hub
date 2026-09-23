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
 * Step 3 (plan §3.5 step 7, R11.4): the closing parts, written once every
 * section exists so the checklist can name what actually happens on screen.
 */
final class GenerateScriptClosingAgent implements Agent, Conversational, HasProviderOptions, HasStructuredOutput
{
    use Promptable;
    use RemembersConversations;
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
            - during_recording: timing and emphasis cues tied to section numbers, and
              always these recording and editing rules: never read a prompt aloud,
              only its bridge sentence; keep talking while the tool generates, the
              excess is trimmed in editing; one breath per narration paragraph; no
              recording fillers ("pausa acá", "a ver", "vale"); after a mistake,
              repeat the WHOLE sentence from its start so the cut lands cleanly;
              cut only at the "[CORTE]" notes, on a still frame, leaving about
              150 ms of air before and after each cut so no word is clipped; record
              every "[SIN CORTE]" step whole; compress generation waits longer than
              3 seconds (speed the video up, never the voice); normalise the audio
              to −16 LUFS.
            - tools_required: tools, apps or connectors the recording needs; when none
              are needed, leave it empty and explain in tools_none_reason.
            - continuity: how this video connects with the previous and next ones.
            - organisations_used: the fictional organisations that appear. Only names
              from the course bible or from the practice pack.
            - verification_checklist: what must visibly happen on screen, one item per
              demonstration, plus format checks — always including: no prompt read
              aloud; no audible mini-pauses inside a paragraph; every cut clean, on
              a still frame and with no clipped word; every menu or scene change
              shown whole; final duration within ±10 seconds of the target.
            - practice_exercise: the EJERCICIO PRÁCTICO the student does on their own
              after the video — every video has one, conceptual ones included.
              title: a short, inviting name. scenario: a concrete, realistic
              situation from the student's own work or life (2–4 sentences) that
              makes them want to try it. task: exactly what to produce, reusing
              what the video taught, without copying a demo step by step.
              success_criteria: 2–4 observable checks that tell the student they
              got it right.
            - Write in the course language. Fix every correction listed in the request.
            INSTRUCTIONS."\n\n".UntrustedContentBlock::DIRECTIVE;
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
            'practice_exercise' => $schema->object(static fn (JsonSchema $schema): array => [
                'title' => $schema->string()->required(),
                'scenario' => $schema->string()->required(),
                'task' => $schema->string()->required(),
                'success_criteria' => $schema->array()->items($schema->string())->required(),
            ])->required(),
        ];
    }
}
