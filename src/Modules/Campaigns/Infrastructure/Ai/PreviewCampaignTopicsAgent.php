<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Promptable;
use Shared\Infrastructure\AI\PromptCache\UsesPromptCache;
use Stringable;

/**
 * Text-only twin of {@see SuggestCampaignTopicsAgent} for the wizard SSE
 * preview. The SDK cannot stream structured-output agents, so the preview
 * runs without a schema: same auditor persona, same cacheable prefix, but
 * the contract is a numbered Markdown list instead of validated JSON.
 * Nothing it returns is stored — the authoritative list still comes from the
 * JSON endpoint.
 */
final class PreviewCampaignTopicsAgent implements Agent, Conversational, HasProviderOptions
{
    use Promptable, UsesPromptCache;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a ruthless Meta Ads performance auditor. A media buyer is
            about to put real budget behind these angles, so every idea must
            earn its place: concrete hooks, real pain points, no hype.

            Output exactly 10 Meta Ads campaign angles as a numbered Markdown
            list, one per line, in this exact shape:

            1. **Title** — hook (platform, funnel stage)

            Platform is one of facebook, instagram. Funnel stage is one of
            tofu, mofu, bofu, loyalty. Balance the stages. No intro, no outro,
            no blank lines between items — only the 10 lines.
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
