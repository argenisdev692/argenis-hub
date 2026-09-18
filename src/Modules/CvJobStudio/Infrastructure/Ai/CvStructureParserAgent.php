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
 * Parses raw CV text into addressable structure (T-068, FR-4): profile facts,
 * entries, bullets and skills with evidence. No prompt caching — unique input
 * per parse (CHG-20).
 */
final class CvStructureParserAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Parse this CV into structured data: profile_facts (name, city,
            country, links, contacts); entries (experience, project, education,
            certification, achievement, language — each with organization,
            role_title, location, dates); bullets (per entry, in order, with
            has_metric and xyz_complete); skills (canonical_name, nature
            hard/soft/tool/language, evidence list_only/in_bullet/both).
            Never invent facts not present in the text.
            INSTRUCTIONS;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profile_facts' => $schema->object()->required(),
            'entries' => $schema->array()->items($schema->object()->required())->required(),
            'bullets' => $schema->array()->items($schema->object()->required())->required(),
            'skills' => $schema->array()->items($schema->object()->required())->required(),
        ];
    }
}
