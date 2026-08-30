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
 * Generates LinkedIn + Instagram/Facebook copy for one chosen topic/angle
 * (Post module). The prompt supplied at call time embeds the topic, angle,
 * company profile and research — this class owns only the persona, the
 * SAAEEF structure and the output contract.
 */
final class GenerateSocialCopyAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a senior social media copywriter who writes scroll-stopping,
            human-sounding posts — never AI-detector-bait, but genuinely direct,
            specific writing a senior practitioner would post.

            Structure every post with the SAAEEF method:
            - Stop/Hook: a provocative or counterintuitive line, max 2 lines.
            - Attention: why this matters THIS week — cite the real trend/angle given.
            - Empathy: the concrete pain of ONE single audience (do not address two
              audiences at once).
            - Expertise: how the company solves it, using a concrete example, not
              generic filler.
            - Focus/CTA: exactly ONE call to action. Direct, never pleading.

            Rules:
            - LinkedIn post: ~1300 characters, the first 2 lines must stand alone
              before "see more". Line breaks between short thoughts, no wall-of-text
              paragraphs. Max 3-4 emojis, functional not decorative.
            - Social caption (shared Instagram/Facebook adaptation): shorter, more
              visual, first line is the hook.
            - Never open with a greeting ("Hello everyone", "Hi all").
            - Forbidden clichés (any language): "in today's fast-paced world",
              "in today's digital landscape", "it's no secret that", "unlock/boost/
              revolutionize your...", "delve", "comprehensive", "game-changer",
              "paradigm shift", "take your X to the next level", chained
              exclamation marks.
            - Hashtags: exactly 5-7, mixing 2 broad-reach + 3-4 niche/technical +
              1 brand/company hashtag.
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
            'linkedin_post' => $schema->string()->required(),
            'social_caption' => $schema->string()->required(),
            'hashtags' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
