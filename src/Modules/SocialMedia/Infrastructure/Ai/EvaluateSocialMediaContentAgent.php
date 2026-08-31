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
use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;
use Stringable;

/**
 * Step 2, the JUDGE: scores a draft written by
 * {@see GenerateSocialMediaContentAgent} and never writes content itself.
 *
 * It runs on `config('ai.default_for_evaluation')` — a different provider from
 * the writer. That is the entire reason this class exists: the writer used to
 * emit its own scores in the same response, which is self-assessment, and
 * self-assessment is generous. The five thresholds in
 * {@see ContentQualityEvaluator} only mean something when an outside model
 * applies them.
 *
 * Pass/fail is still computed in PHP, never here: the judge reports numbers,
 * the evaluator service decides.
 */
final class EvaluateSocialMediaContentAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a ruthless social-media content critic scoring another
            model's draft. You did not write it, you have no stake in it, and
            your value comes entirely from being harder to please than the
            writer. Generous scores are a failure of your job.

            Score each dimension 0-100. Calibrate like this:
            - 90-100: genuinely exceptional, would stand out on a busy feed.
            - 75-89: solid professional work, ships.
            - 60-74: competent but forgettable; a real weakness is present.
            - 40-59: generic; reads like every other post on the topic.
            - 0-39: broken, off-brief, or obviously machine-written.
            Most first drafts land in 55-75. Do not cluster everything at 80.

            === human_writing_index (the gatekeeper) ===
            Penalize hard, without mercy: banned LLM phrasing ("in today's
            fast-paced world", "it's important to note", "unlock/boost/
            revolutionize", "dive into", "game-changer", "comprehensive
            solution", "take your X to the next level", "in conclusion",
            "needless to say", chained exclamation marks, opening greetings)
            in ANY language; uniform sentence and paragraph length; vague
            claims with no number or date; symmetrical tricolon structures;
            an absent authorial point of view. Reward specific failures with
            real numbers and dates, honest hedging, and irregular rhythm.

            === virality_score ===
            Judge the first 1-2 lines ALONE, as they appear before a "see
            more" cut. Does the hook stop a scroll — a shocking statistic, a
            provocative question, a contrarian take, a specific failure
            number? Is there a trend or data point from the last 90 days? Is
            one emotional trigger targeted clearly?

            === engagement_score ===
            Does every platform variation deliver standalone value, or is it a
            teaser gated behind a click? Is there an explicit interaction
            prompt, and does it match the funnel stage in the brief?

            === roi_score ===
            Does the draft position the author as the go-to expert for THIS
            specific problem? Is there a path to go deeper, sized to the
            funnel stage (TOFU: no ask - MOFU: discussion or resource - BOFU:
            direct, message/call/work together)? A TOFU post with a hard sell
            scores low here, not high.

            === trend_alignment ===
            Is a current platform-native trend or format referenced (threads
            on Twitter/X, carousels and Reels on Instagram, trending sound
            types on TikTok)? Is any trend claim supported by the research
            supplied in the brief, or merely asserted?

            === Rules for every score ===
            `factors` breaks the score into its named sub-components so the UI
            can show why it landed where it did; they must be consistent with
            the headline value, not decorative.
            `explanation` must name what specifically failed and what to
            change — it is fed verbatim into the next iteration's rewrite
            prompt, so "could be stronger" is useless. Say WHICH line, and
            WHAT to do to it.

            === eeat_analysis ===
            List only signals actually present in the draft. An empty list is
            the correct answer when a dimension is missing — never invent one
            to look thorough.

            === ai_detection_risk ===
            Estimate 0-100 (higher = more likely to be flagged as AI-written).
            `label` is one of "low", "medium", "high".

            === optimization_suggestions ===
            3-6 concrete, actionable rewrites. No praise, no summary of what
            the draft already does well.
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
                'human_writing_index' => $scoreField($schema, ['natural_language', 'personal_anecdotes', 'varied_structure', 'emotional_depth']),
                'virality_score' => $scoreField($schema, ['hook_strength', 'shareability', 'timing', 'emotional_trigger']),
                'engagement_score' => $scoreField($schema, ['cta_strength', 'interaction_prompt', 'value_density', 'emotional_connection']),
                'roi_score' => $scoreField($schema, ['conversion_potential', 'brand_alignment', 'lead_generation']),
                'trend_alignment' => $scoreField($schema, ['current_trend', 'timeliness', 'platform_format']),
            ])->required(),

            'eeat_analysis' => $schema->object(fn ($schema) => [
                'experience_signals' => $schema->array()->items($schema->string())->required(),
                'expertise_signals' => $schema->array()->items($schema->string())->required(),
                'authoritativeness_signals' => $schema->array()->items($schema->string())->required(),
                'trustworthiness_signals' => $schema->array()->items($schema->string())->required(),
            ])->required(),

            'ai_detection_risk' => $schema->object(fn ($schema) => [
                'value' => $schema->integer()->min(0)->max(100)->required(),
                'label' => $schema->string()->required(),
                'explanation' => $schema->string()->required(),
            ])->required(),

            'optimization_suggestions' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
