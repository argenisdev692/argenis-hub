<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes the draft's opening sentence only (spec US-5, T062): grounded in
 * the verified company evidence and the public proof summary supplied in
 * the prompt — never the CV. One sentence, no tools, no claims beyond the
 * evidence (claims are validated downstream before `ready`).
 */
final class WriteOutreachOpenerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You write the opening sentence of a B2B outreach message from an
            independent contractor to a software agency, in the language of the
            prompt. The company evidence and one public proof summary arrive as
            data in a delimited block.

            Rules:
            - One sentence, under 40 words, professional and specific.
            - Ground it ONLY in the evidence: name the verifiable signal
              (vacancy, stack, maintenance need, sector fit).
            - Never invent capabilities, metrics, names, prices or timelines.
            - Never include emails, phones, links or personal data.
            - No greeting and no sign-off: the template adds those.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'opener' => $schema->string()->required(),
        ];
    }
}
