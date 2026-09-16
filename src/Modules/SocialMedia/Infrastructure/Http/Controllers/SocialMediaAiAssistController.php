<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Modules\SocialMedia\Application\Commands\GenerateSocialMediaContentHandler;
use Modules\SocialMedia\Application\Commands\SuggestSocialMediaTopicsHandler;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\SuggestSocialMediaTopicsData;
use Modules\SocialMedia\Application\Queries\GetSocialMediaContentHandler;
use Modules\SocialMedia\Domain\Ports\SocialMediaTopicIdeatorPort;

/**
 * XHR-only AI actions for the 2-step wizard. Step 1 (`suggestTopics`) is
 * synchronous — it is a single provider call, same cost profile as Post's
 * AI-assist. Step 2 (`generateContent`) returns immediately with a
 * `generating` row; the frontend polls `status()` or subscribes to
 * `social-media.ai.progress` on its private channel instead of blocking on
 * the up-to-5-iteration quality loop. Gated by `permission:CREATE_SOCIAL_MEDIA`
 * + a tight `throttle:` (real, billed provider cost per call) — see Routes.
 */
final readonly class SocialMediaAiAssistController
{
    public function __construct(
        private SuggestSocialMediaTopicsHandler $suggestTopics,
        private GenerateSocialMediaContentHandler $generateContent,
        private GetSocialMediaContentHandler $getContent,
    ) {}

    public function suggestTopics(SuggestSocialMediaTopicsData $data, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->suggestTopics->handle($data, $request->user())]);
    }

    /**
     * SSE preview of the topic list: same research + cacheable prompt as the
     * JSON endpoint, streamed token by token. The response is `Responsable` —
     * returned directly, never wrapped.
     */
    public function streamTopics(SuggestSocialMediaTopicsData $data, SocialMediaTopicIdeatorPort $ideator): StreamableAgentResponse
    {
        return $ideator->streamTopics($data);
    }

    public function generateContent(GenerateSocialMediaContentData $data, Request $request): JsonResponse
    {
        $content = $this->generateContent->handle($data, $request->user());

        return response()->json(['data' => $content], 202);
    }

    public function status(string $uuid): JsonResponse
    {
        return response()->json(['data' => $this->getContent->handle($uuid)]);
    }
}
