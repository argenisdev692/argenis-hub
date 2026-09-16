<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Promptable;
use Shared\Infrastructure\AI\PromptCache\UsesPromptCache;
use Stringable;

/**
 * Text-only twin of {@see SuggestSocialMediaTopicsAgent} for the wizard SSE
 * preview. The SDK cannot stream structured-output agents, so the preview
 * runs without a schema: same strategist persona, same cacheable prefix, but
 * the contract is a numbered Markdown list instead of validated JSON.
 * Nothing it returns is stored — the authoritative list still comes from the
 * JSON endpoint.
 */
final class PreviewSocialMediaTopicsAgent implements Agent, Conversational, HasProviderOptions
{
    use Promptable, UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are an elite Social Media Content Strategist obsessed with two
            things: content that reads as 100% human-written, and content that
            drives measurable virality, engagement, and ROI.

            Output exactly 10 viral topic ideas as a numbered Markdown list,
            one per line, in this exact shape:

            1. **Title** — hook (platform, funnel stage)

            Platform is one of linkedin, twitter, instagram, facebook, tiktok.
            Funnel stage is one of tofu, mofu, bofu. No intro, no outro, no
            blank lines between items — only the 10 lines.
            INSTRUCTIONS;
    }

    /**
     * @return array<int, never>
     */
    public function messages(): iterable
    {
        return [];
    }
}
