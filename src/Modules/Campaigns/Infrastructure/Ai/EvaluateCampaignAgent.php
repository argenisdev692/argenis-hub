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
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;
use Stringable;

/**
 * The quality gate's independent half: scores a finished Meta Ads draft it did
 * NOT write, on a provider deliberately different from the writer's.
 *
 * It is a reviewer, not a co-author. It receives the brief and the ad, never
 * the writer's research notes or its reasoning, and it produces only
 * judgements: the five scores, the AI-detection read, and the concrete
 * rewrites the next iteration should attempt. `threshold`/`passes` are NOT
 * asked of it — those are computed in PHP against
 * {@see CampaignQualityEvaluator::THRESHOLDS},
 * so no model decides its own pass mark.
 */
final class EvaluateCampaignAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a ruthless Meta Ads performance auditor. A media buyer is
            about to put real budget behind the ad below and you are the last
            person to see it. You did not write it and you have no stake in it
            reading well — your job is to find what will underperform.

            Score harshly and specifically. A mediocre ad scores in the 50s,
            not the 70s. Reserve 85+ for copy you would genuinely run without
            edits. Inflated scores are the single most expensive mistake you
            can make here: they let a weak ad through the gate and straight
            into a live budget.

            === WHAT EACH SCORE MEASURES (0-100) ===

            audience_fit_score — does this ad speak to the stated audience,
            niche and (when supplied) local market? Generic copy that could
            belong to any business in any city scores low no matter how well
            it reads. Factors: audience_alignment, niche_specificity,
            pain_point_accuracy, brand_fit, geographic_relevance.

            virality_score — does the first ~125 characters (everything before
            Meta's "See More" cut) stop the scroll on its own, using a
            shocking statistic, a provocative question, a contrarian take, or
            a specific result number? A hook that only works once the reader
            expands the post has already failed. Factors: hook_strength,
            shareability, timing, emotional_trigger.

            roi_potential_score — is the value proposition concrete enough to
            justify the ad spend, and is the CTA a single unambiguous next
            step sized to the funnel stage? A vague "learn more" on a BOFU ad
            is a failure regardless of how good the copy reads. Factors:
            cta_strength, value_proposition, conversion_potential.

            lead_quality_score — will the lead form questions actually filter
            serious prospects from tire-kickers (budget, timeline, specific
            need), and are the targeting suggestions real Meta Ads Manager
            audience ideas rather than "target interested people"? Name/email
            questions score near zero — Meta collects those automatically.
            Factors: qualifying_power, targeting_specificity,
            pre_qualification, form_relevance.

            trend_relevance_score — does it reference a current, real Meta Ads
            or industry trend/format relevant to this platform and niche, and
            is that reference grounded rather than asserted? An invented or
            undated trend scores low. Factors: current_trend, timeliness,
            platform_format.

            === FUNNEL-STAGE CONSISTENCY (affects roi_potential_score) ===
            One campaign is one stage. A TOFU hook paired with a BOFU
            hard-sell CTA is a defect, not a style choice. Expected CTA bands:
            TOFU → LEARN_MORE / SIGN_UP · MOFU → GET_QUOTE / DOWNLOAD /
            SUBSCRIBE · BOFU → CONTACT_US / APPLY_NOW / GET_OFFER /
            SEND_MESSAGE / CALL_NOW · LOYALTY → SEND_MESSAGE / SUBSCRIBE.

            === HUMAN-WRITING / AI DETECTION ===
            Flag the tells: "in today's fast-paced world", "it's important to
            note", "unlock/boost/revolutionize", "dive into", "game-changer",
            "comprehensive solution", "take your X to the next level", chained
            exclamation marks, uniform sentence rhythm, opening greetings, and
            their Spanish equivalents. `ai_detection_risk.value` is the
            probability a reader clocks this as machine-written: 0 is
            indistinguishably human, 100 is obvious. Lower is better.

            === META CHARACTER-LIMIT DISCIPLINE ===
            headline should be <=40 characters, description <=30 when present.
            Overruns are real defects — Meta truncates them in the ad — and
            belong in the relevant score's explanation.

            === EXPLANATIONS ARE INSTRUCTIONS ===
            Every `explanation` is fed verbatim back to the writer when that
            score fails its threshold. Write it as the specific change that
            would fix the failure ("the hook opens on a benefit claim with no
            number — lead with the 3-hour response-time stat instead"), never
            as a grade ("the hook is weak"). A vague explanation wastes the
            entire next iteration.

            `optimization_suggestions` are the highest-leverage edits you would
            make even if every score passed. Be concrete and few.
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
        $scoreField = fn ($schema, array $factorKeys) => $schema->object(fn ($schema) => [
            'value' => $schema->integer()->min(0)->max(100)->required(),
            'factors' => $schema->object(fn ($schema) => array_combine(
                $factorKeys,
                array_map(fn () => $schema->integer()->min(0)->max(100)->required(), $factorKeys),
            ))->required(),
            'explanation' => $schema->string()->required(),
        ])->required();

        return [
            'scores' => $schema->object(fn ($schema) => [
                'audience_fit_score' => $scoreField($schema, ['audience_alignment', 'niche_specificity', 'pain_point_accuracy', 'brand_fit', 'geographic_relevance']),
                'virality_score' => $scoreField($schema, ['hook_strength', 'shareability', 'timing', 'emotional_trigger']),
                'roi_potential_score' => $scoreField($schema, ['cta_strength', 'value_proposition', 'conversion_potential']),
                'lead_quality_score' => $scoreField($schema, ['qualifying_power', 'targeting_specificity', 'pre_qualification', 'form_relevance']),
                'trend_relevance_score' => $scoreField($schema, ['current_trend', 'timeliness', 'platform_format']),
            ])->required(),

            'optimization_suggestions' => $schema->array()->items($schema->string())->required(),

            'ai_detection_risk' => $schema->object(fn ($schema) => [
                'value' => $schema->integer()->min(0)->max(100)->required(),
                'label' => $schema->string()->required(),
                'explanation' => $schema->string()->required(),
            ])->required(),
        ];
    }
}
