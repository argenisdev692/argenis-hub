<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * ATS-safe rewrite (T-073, FR-5…FR-8): the protected block passes through
 * unchanged, no absent skill is introduced, every output bullet names its
 * source bullet, and the result fits 2 pages. No prompt caching — unique
 * input per rewrite (CHG-20).
 */
final class RewriteCvAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Rewrite this CV into an ATS-safe form: single-column, standard
            section headings, MM/YYYY dates, at most 2 pages. Carry the
            protected block through UNCHANGED. Never introduce a skill absent
            from the source. Record which source bullet each output bullet
            derives from, and what was cut to fit.
            INSTRUCTIONS;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sections' => $schema->array()->items(
                $schema->object(
                    heading: $schema->string()->required(),
                    bullets: $schema->array()->items(
                        $schema->object(
                            text: $schema->string()->required(),
                            source_bullet: $schema->string()->required(),
                        )
                    )->required(),
                )
            )->required(),
            'cut_notes' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
