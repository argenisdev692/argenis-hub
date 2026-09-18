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
 * ES / EN / PT-PT variants (T-078, FR-23): the untranslatable-terms list and
 * the version content form the long-lived cached layer, the target language
 * the tail — so three languages share one cached prefix. Technical terms
 * that must stay in English are never translated.
 */
final class TranslateCvAgent implements Agent, HasStructuredOutput
{
    use Promptable;
    use UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Translate this CV version into the requested language (es, en or
            pt-PT), applying the language to section headings and body alike.
            Keep every term from the untranslatable list in English exactly.
            Never add, remove or reword experience facts — translate only.
            INSTRUCTIONS;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sections' => $schema->array()->items(
                $schema->object(
                    heading: $schema->string()->required(),
                    bullets: $schema->array()->items($schema->string())->required(),
                )
            )->required(),
        ];
    }
}
