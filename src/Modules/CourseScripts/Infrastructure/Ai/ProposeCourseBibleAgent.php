<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Proposes the recurring fiction and voice of a video-pill course (US-3).
 *
 * Modelled on the author's reference material: one primary fictional company
 * the audience works in (Tecnoform S.A.), a few named characters, recurring
 * secondary organisations (clients, suppliers such as Heliantia Group), a
 * narration tone and the tool being taught, if any.
 */
final class ProposeCourseBibleAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You design the "course bible" of a short video course: the fiction and
            voice every script and every practice document of the course will
            share, so that video 40 uses the same company, people and tone as
            video 3.

            Produce:
            - organisations: exactly ONE primary organisation — the invented company
              the audience works in, where the examples happen — plus 1 to 4
              secondary ones that will recur (clients, suppliers, partners).
              Every organisation is INVENTED: never a real company or brand.
              Give each a short snake_case key, a name that sounds plausible in
              the course language and market, its role in the fiction and its
              sector.
            - characters: 2 to 5 recurring invented people with a first name,
              their job, and the key of their organisation. Never real people.
            - audience: who watches the course, in one or two sentences.
            - tone: how the narration sounds, in one sentence.
            - taught_tool: the software or product the presenter operates on
              screen (for example "Claude", "Excel", "Cursor"), or an empty
              string when the subject is not taught through a tool the presenter
              types into.
            - forbidden_phrasings: up to 6 phrasings the narration should avoid
              (hype, jargon the audience would not use, promises).

            When the author's notes already name a company, people or a tool, use
            those instead of inventing new ones. Write every value in the course
            language given in the prompt.

            INSTRUCTIONS.UntrustedContentBlock::DIRECTIVE;
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
            'organisations' => $schema->array()
                ->items($schema->object(fn ($schema): array => [
                    'key' => $schema->string()->required(),
                    'name' => $schema->string()->required(),
                    'role' => $schema->string()->required(),
                    'sector' => $schema->string()->required(),
                    'is_primary' => $schema->boolean()->required(),
                ]))
                ->required(),
            'characters' => $schema->array()
                ->items($schema->object(fn ($schema): array => [
                    'name' => $schema->string()->required(),
                    'role' => $schema->string()->required(),
                    'organisation_key' => $schema->string()->required(),
                ]))
                ->required(),
            'audience' => $schema->string()->required(),
            'tone' => $schema->string()->required(),
            'taught_tool' => $schema->string()->required(),
            'forbidden_phrasings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
