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
 * Blunt recruiter-scan audit (T-071, FR-2): verdict with reasons plus at most
 * 6 metric questions. Every number it mentions is labelled a heuristic
 * (NFR-3). No prompt caching — the input is unique per CV (CHG-20).
 */
final class JudgeCvAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Audit this CV like a blunt recruiter and an ATS parser. Return a
            10-second-scan verdict of pass, borderline or reject_risk with
            concrete reasons; strengths; improvements; keyword gaps; bullets
            that lack a measurable result; and at most 6 metric questions whose
            answers would let a rewrite add real metrics. Every number is a
            heuristic — never present one as a vendor ATS score.
            INSTRUCTIONS;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'verdict' => $schema->string()->required(),
            'verdict_reasons' => $schema->array()->items($schema->string())->required(),
            'strengths' => $schema->array()->items($schema->string())->required(),
            'improvements' => $schema->array()->items($schema->string())->required(),
            'keyword_gaps' => $schema->array()->items($schema->string())->required(),
            'xyz_gaps' => $schema->array()->items($schema->string())->required(),
            'metric_questions' => $schema->array()->items($schema->string())->maxItems(6)->required(),
        ];
    }
}
