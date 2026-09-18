<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Shared\Infrastructure\AI\PromptCache\UsesPromptCache;
use Stringable;

/**
 * Per-posting tailoring (T-077, FR-22): re-orders and re-words skills and
 * summary toward the posting's vocabulary without introducing absent skills.
 * Cached CV snapshot + protected block as the long-lived layer, the posting's
 * requirements and evidence as the tail.
 */
final class TailorCvAgent implements Agent, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Tailor this CV to the posting below: re-order and re-word the
            summary and skills toward the posting's vocabulary. Never introduce
            a skill absent from the source CV. Record the source bullet behind
            every reworded bullet.
            INSTRUCTIONS;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'skills' => $schema->array()->items($schema->string())->required(),
            'provenance' => $schema->array()->items(
                $schema->object(
                    output_bullet: $schema->string()->required(),
                    source_bullet: $schema->string()->required(),
                )
            )->required(),
        ];
    }
}
