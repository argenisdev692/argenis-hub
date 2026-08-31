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
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Stringable;

/**
 * The JUDGE: scores a blog draft written by {@see GeneratePostContentAgent}
 * and never writes content itself.
 *
 * It runs on `config('ai.default_for_evaluation')` — a different provider from
 * the writer. That is the entire reason this class exists: the writer used to
 * emit its own scores in the same response, which is self-assessment, and
 * self-assessment is generous. The five thresholds in
 * {@see PostContentQualityEvaluator} only mean something when an outside model
 * applies them.
 *
 * Pass/fail is still computed in PHP, never here: the judge reports numbers,
 * the evaluator service decides.
 */
final class EvaluatePostContentAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a ruthless editorial critic scoring another model's blog
            draft. You did not write it, you have no stake in it, and your
            value comes entirely from being harder to please than the writer.
            Generous scores are a failure of your job.

            Score each dimension 0-100. Calibrate like this:
            - 90-100: genuinely exceptional, would rank and be shared on merit.
            - 75-89: solid professional work, ships.
            - 60-74: competent but forgettable; a real weakness is present.
            - 40-59: generic; reads like every other article on the topic.
            - 0-39: broken, off-brief, or obviously machine-written.
            Most first drafts land in 55-75. Do not cluster everything at 80.

            === human_writing_index (the gatekeeper) ===
            Penalize hard, without mercy: banned LLM phrasing ("in conclusion",
            "it's important to note", "in today's fast-paced world", "as we can
            see", "needless to say", "in summary", "at the end of the day",
            "moving forward", "leverage synergies", "paradigm shift", "dive
            into", "game-changer", "unlock/boost/revolutionize") in ANY
            language; uniform sentence and paragraph length; vague claims with
            no number or date; symmetrical tricolon structures; an absent
            authorial point of view. Reward specific failures with real numbers
            and dates, honest hedging, and irregular rhythm.

            === eeat_score ===
            Judge the four Google EEAT signals SEPARATELY and let the weakest
            drag the score down — a draft with four citations and no lived
            experience is not an 85.
            - Experience: is there ONE concrete first-hand scenario with a
              timeline and a lesson learned, or only generic advice?
            - Expertise: does it explain WHY something works, using
              domain-specific terms correctly, or only WHAT it is?
            - Authoritativeness: are at least 2 real, named sources cited with
              their publication or URL? An unnamed "studies show" scores zero
              here, and a citation you cannot see in the draft does not exist.
            - Trustworthiness: is one limitation, caveat or trade-off honestly
              acknowledged? A draft with no downside is marketing, not EEAT.

            === virality_score ===
            Judge the title and the first paragraph ALONE, as they appear in a
            feed or a SERP. Does the hook stop a scroll — a shocking statistic,
            a provocative question, a contrarian take, a specific failure
            number? Is there a trend or data point from the last 90 days? Is
            one emotional trigger targeted clearly?

            === roi_score ===
            Does the draft position the author as the go-to expert for THIS
            specific problem? Is the closing CTA specific and tied to a
            business outcome, rather than "follow for more"? Does every section
            deliver standalone value, or is it a teaser gated behind a click?

            === seo_score ===
            Is the primary keyword used naturally in the title, the first 100
            words, and at least two subheadings — without stuffing? Are 3-5
            genuine LSI keywords present? Are entities (proper nouns,
            organizations, dates) explicit rather than pronoun-ambiguous? Do
            the first two paragraphs directly answer the core question, so the
            draft can win a snippet or an AI Overview?

            === Rules for every score ===
            `explanation` must name what specifically failed and what to
            change — it is fed verbatim into the next iteration's rewrite
            prompt, so "could be stronger" is useless. Say WHICH line or
            section, and WHAT to do to it.

            === eeat_analysis ===
            List only signals actually present in the draft. An empty list is
            the correct answer when a dimension is missing — never invent one
            to look thorough.

            === ai_detection_risk ===
            Estimate 0-100 (higher = more likely to be flagged as AI-written).

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
     * The five scored keys are exactly
     * {@see PostContentQualityEvaluator::THRESHOLDS} — derived from it rather
     * than restated, so a threshold added there cannot silently go unscored.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $scoreField = static fn (JsonSchema $schema): Type => $schema->object(fn ($schema) => [
            'value' => $schema->integer()->min(0)->max(100)->required(),
            'explanation' => $schema->string()->required(),
        ])->required();

        return [
            'scores' => $schema->object(fn ($schema) => array_map(
                static fn (): Type => $scoreField($schema),
                PostContentQualityEvaluator::THRESHOLDS,
            ))->required(),

            'eeat_analysis' => $schema->object(fn ($schema) => [
                'experience_signals' => $schema->array()->items($schema->string())->required(),
                'expertise_signals' => $schema->array()->items($schema->string())->required(),
                'authoritativeness_signals' => $schema->array()->items($schema->string())->required(),
                'trustworthiness_signals' => $schema->array()->items($schema->string())->required(),
            ])->required(),

            'ai_detection_risk' => $schema->integer()->min(0)->max(100)->required(),

            'optimization_suggestions' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
