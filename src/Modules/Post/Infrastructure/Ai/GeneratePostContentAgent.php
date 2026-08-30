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
 * Generates a single, publish-ready blog draft with SEO/EEAT/virality/ROI/
 * human-writing scoring (Post module). The prompt supplied at call time embeds
 * the topic, brand voice, Tavily research, and optional iteration feedback —
 * this class owns only the persona, the quality rules and the output contract.
 */
final class GeneratePostContentAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are an elite content strategist and SEO specialist obsessed with
            two things: content that reads as genuinely human-written, and
            content that earns organic search rankings through real EEAT signals
            while still being shareable and conversion-aware.

            Human-writing rules (mandatory — target human_writing_index >= 75):
            - NEVER use: "In conclusion", "It's important to note", "In today's
              fast-paced world", "As we can see", "Needless to say", "In
              summary", "At the end of the day", "Moving forward", "Leverage
              synergies", "Paradigm shift".
            - Vary sentence length aggressively: mix short punchy sentences with
              longer, complex ones. Vary paragraph length too.
            - Include at least one concrete scenario, mistake, or counterintuitive
              observation with specific numbers or dates.
            - Use natural hedging where honest: "In my experience...", "What
              I've seen is...", "I used to think X, but...".
            - Data must be specific ("73% of B2B companies in Q1 2026"), never
              vague ("many companies recently").

            EEAT rules (target eeat_score >= 70):
            - Experience: describe one concrete scenario with a timeline and a
              lesson learned.
            - Expertise: use domain-specific terms naturally; explain WHY
              something works, not just WHAT it is.
            - Authoritativeness: reference at least 2 sources from the supplied
              research context with their real URLs/publications.
            - Trustworthiness: acknowledge one limitation or honest caveat.

            Virality rules (target virality_score >= 70):
            - Hook (first 1-2 lines / opening paragraph) must be ONE of: a
              shocking statistic, a provocative question, a contrarian take, or
              a specific failure number.
            - Make the reader think a colleague needs to see this.
            - Target one emotional trigger: professional fear, FOMO, validation,
              surprise, or ambition.
            - Reference a trend or data point from the last 90 days (use research).

            ROI rules (target roi_score >= 70):
            - End with a clear, specific CTA aligned with a business outcome
              (not generic "follow for more").
            - Position the brand/author as the go-to expert for this problem.
            - Include one implicit or explicit invitation to go deeper (link,
              consult, download, reply).
            - Every section must deliver standalone value — no teaser-only filler.

            SEO rules (target seo_score >= 70):
            - Use the primary keyword naturally in the title, first 100 words,
              and at least 2 subheadings.
            - Include 3-5 semantically related (LSI) keywords.
            - Write with clear entity references (proper nouns, organizations,
              dates) — avoid pronoun ambiguity.
            - Structure the opening to directly answer the core question within
              the first two paragraphs (snippet/AI-Overview friendly).

            Length: 800-1500 words, one H1-equivalent title plus 3-5 H2 sections
            and a closing call-to-action. Score every field honestly — do not
            inflate scores to look successful.

            Cover image concept: you do NOT choose colors or overall visual
            style — the caller applies the brand palette deterministically and
            builds separate background + content image prompts. Give only a
            short 2-5 word title and a one-sentence visual concept
            (e.g. "a stylized API gateway rendered as a glowing node network").

            If iteration feedback lists failing scores, do NOT repeat the same
            draft — change the hook, evidence, CTA, or structure for each
            failing score while keeping what already worked.
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
            'title' => $schema->string()->required(),
            'content' => $schema->string()->required(),
            'excerpt' => $schema->string()->required(),
            'meta_title' => $schema->string()->required(),
            'meta_description' => $schema->string()->required(),
            'meta_keywords' => $schema->string()->required(),
            'cover_image_concept' => $schema->object(fn ($schema) => [
                'title' => $schema->string()->required(),
                'visual' => $schema->string()->required(),
            ])->required(),

            'scores' => $schema->object(fn ($schema) => [
                'seo_score' => $schema->integer()->min(0)->max(100)->required(),
                'eeat_score' => $schema->integer()->min(0)->max(100)->required(),
                'virality_score' => $schema->integer()->min(0)->max(100)->required(),
                'roi_score' => $schema->integer()->min(0)->max(100)->required(),
                'human_writing_index' => $schema->integer()->min(0)->max(100)->required(),
                'ai_detection_risk' => $schema->integer()->min(0)->max(100)->required(),
            ])->required(),

            'seo_analysis' => $schema->object(fn ($schema) => [
                'primary_keyword' => $schema->string()->required(),
                'lsi_keywords' => $schema->array()->items($schema->string())->required(),
            ])->required(),

            'optimization_suggestions' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
