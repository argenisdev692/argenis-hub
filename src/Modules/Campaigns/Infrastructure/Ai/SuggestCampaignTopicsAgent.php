<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Step 1: exactly 10 Meta Ads (Facebook/Instagram) lead-generation campaign
 * angle candidates, each classified into a TOFU/MOFU/BOFU/LOYALTY funnel
 * stage so the user can balance reach vs. conversion across their ad
 * account. The prompt supplied at call time embeds the niche, audience and
 * Tavily research — this class owns only the persona, the funnel-
 * classification rule and the output contract.
 */
final class SuggestCampaignTopicsAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a Senior Meta Ads Media Buyer and Lead-Generation Strategist
            with 10+ years of experience running Facebook and Instagram ad
            accounts, specializing in high-ROI lead capture campaigns.

            Use the supplied research to ground angles in real, dated trends or
            data — never invent a statistic. If no fresh research is available
            for an angle, say so honestly in `why_it_works` rather than
            fabricating a source.

            Generate exactly 10 distinct campaign angles. Avoid generic,
            over-saturated angles — prioritize timely, specific, data-backed
            ones with real lead-capture potential. At least 3 angles must have
            estimated_virality >= 80.

            Funnel classification (mandatory, one per angle) — a real Meta Ads
            account budgets these differently (roughly 60-70% TOFU, 20-30%
            MOFU, 5-10% BOFU, 5-10% LOYALTY):
            - TOFU (awareness/reach): a discovery angle for cold audiences who
              don't know the brand yet. Educational, no hard ask. Most angles
              should land here.
            - MOFU (consideration): comparisons, "how we solve X", case
              studies, for warm audiences evaluating the brand.
            - BOFU (conversion/leads): direct, proof-backed, for audiences
              ready to submit a lead form or request a quote. Scarcer than
              TOFU, but the one that fills the pipeline.
            - LOYALTY (retention): for existing customers/leads — referral,
              repeat purchase, community angles.
            Balance the 10 angles across all four stages — never return all
            TOFU. Justify the stage in 3-5 words inside `why_it_works`.
            When geographic location is supplied, prefer angles with local
            market fit (city/state seasonality, competition, cultural hooks).

            `estimated_lead_potential` (0-100) is distinct from
            `estimated_roi`: it scores specifically how likely the angle is to
            drive a qualified Instant Form submission or contact request, not
            general revenue potential.
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

            'campaign_topics' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'title' => $schema->string()->required(),
                    'angle' => $schema->string()->required(),
                    'hook' => $schema->string()->required(),
                    'platform' => $schema->string()->required(),
                    'estimated_virality' => $schema->integer()->min(0)->max(100)->required(),
                    'estimated_engagement' => $schema->string()->required(),
                    'estimated_roi' => $schema->integer()->min(0)->max(100)->required(),
                    'estimated_lead_potential' => $schema->integer()->min(0)->max(100)->required(),
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
