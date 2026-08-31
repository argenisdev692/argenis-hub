<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Step 1: exactly 10 viral topic candidates for LinkedIn/Twitter/Instagram/
 * Facebook/TikTok, each classified into a TOFU/MOFU/BOFU funnel stage so the
 * user can balance reach vs. conversion in their content calendar. The prompt
 * supplied at call time embeds the niche, audience and Tavily research — this
 * class owns only the persona, the funnel-classification rule and the output
 * contract.
 */
final class SuggestSocialMediaTopicsAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a Senior Social Media Content Strategist with 10+ years of
            experience in viral content creation, audience engagement, and trend
            analysis across LinkedIn, Twitter/X, Instagram, Facebook, and TikTok.

            Use the supplied research to ground topics in real, dated news or
            data — never invent a trend. If no fresh research is available for
            an angle, say so honestly in `why_it_works` rather than fabricating
            a source.

            Generate exactly 10 distinct topics. Avoid generic, over-saturated
            angles — prioritize timely, specific, data-backed ones. At least 3
            topics must have estimated_virality >= 80.

            Funnel classification (mandatory, one per topic):
            - TOFU (reach): a discovery/news angle for people who don't know the
              author yet. Educational, no ask. Most topics should land here.
            - MOFU (trust): "how I'd solve X" / comparisons / common mistakes,
              for people already evaluating the author's expertise.
            - BOFU (conversion): "here's my process / result I got", for people
              ready to act. Scarcer than TOFU, but the one that converts.
            Balance the 10 topics across all three stages — never return all
            TOFU. Justify the stage in 3-5 words inside `why_it_works`.
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
                'key_pain_points' => $schema->array()->items($schema->string())->required(),
                'trending_topics' => $schema->array()->items($schema->string())->required(),
                'tavily_insights' => $schema->array()->items($schema->string())->required(),
            ])->required(),

            'viral_topics' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'title' => $schema->string()->required(),
                    'angle' => $schema->string()->required(),
                    'hook' => $schema->string()->required(),
                    'platform' => $schema->string()->required(),
                    'estimated_virality' => $schema->integer()->min(0)->max(100)->required(),
                    'estimated_engagement' => $schema->string()->required(),
                    'estimated_roi' => $schema->integer()->min(0)->max(100)->required(),
                    'difficulty' => $schema->string()->required(),
                    'why_it_works' => $schema->string()->required(),
                    'key_trend' => $schema->string()->required(),
                    'suggested_format' => $schema->string()->required(),
                    'content_type' => $schema->string()->required(),
                    'funnel_stage' => $schema->string()->required(),
                ]))
                ->required(),
        ];
    }
}
