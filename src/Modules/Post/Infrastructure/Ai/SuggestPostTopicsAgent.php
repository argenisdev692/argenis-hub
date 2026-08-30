<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Blog-scoped topic ideation agent (Post module). Grounded in the caller's
 * prompt, which already embeds the company profile and Tavily research
 * summary — this class owns only the persona and the output contract.
 */
final class SuggestPostTopicsAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a Senior Content Strategist with 10+ years of experience in
            viral blog content, SEO, and audience engagement. You specialize in
            identifying high-potential blog topics from a company's profile and
            current web trends.

            Rules:
            1. Use the provided web-research context to ground ideas in real,
               current trends — do not invent statistics or sources.
            2. Generate exactly 10 distinct blog topic ideas that balance
               virality potential, ROI, and EEAT (Experience, Expertise,
               Authoritativeness, Trustworthiness) achievability.
            3. Each idea needs a clear angle, a compelling hook, and honest
               (not inflated) performance estimates.
            4. Avoid generic, over-saturated topics — prioritize specific,
               timely, data-backed angles the company can credibly write about.
            INSTRUCTIONS;
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
            'niche_analysis' => $schema->object(fn ($schema) => [
                'target_audience' => $schema->string()->required(),
                'trending_topics' => $schema->array()->items($schema->string())->required(),
            ])->required(),

            'content_ideas' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'title' => $schema->string()->required(),
                    'angle' => $schema->string()->required(),
                    'hook' => $schema->string()->required(),
                    'estimated_virality' => $schema->integer()->min(0)->max(100)->required(),
                    'estimated_roi' => $schema->integer()->min(0)->max(100)->required(),
                    'eeat_potential' => $schema->integer()->min(0)->max(100)->required(),
                    'why_it_works' => $schema->string()->required(),
                    'key_trend' => $schema->string()->required(),
                ]))
                ->required(),
        ];
    }
}
