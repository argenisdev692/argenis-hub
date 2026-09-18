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
 * The 2–4-sentence "why / gaps" narrative (T-156, US-4): built ONLY from the
 * stored breakdown and ✓/✗ requirements, with NO numeric fields — a test
 * asserts no number in the narrative differs from the stored breakdown
 * (NFR-2). If every provider fails, the posting shows without narrative and
 * its numbers are unchanged.
 */
final class NarrateMatchAgent implements Agent, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Write 2–4 sentences explaining why this candidate matches this
            posting and where the gaps are, using ONLY the match breakdown and
            requirement checklist given. Do not invent numbers, scores or
            percentages — narrate the stated evidence in words.
            INSTRUCTIONS;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'why' => $schema->string()->required(),
            'gaps' => $schema->string()->required(),
        ];
    }
}
