<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Application\Commands\GenerateReelPackageHandler;
use Modules\Post\Application\Commands\GenerateSocialCopyHandler;
use Modules\Post\Application\Commands\SuggestPostTopicsHandler;
use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;

/**
 * XHR-only AI actions consumed by the Create/Edit AI-assist panel. All are
 * read-only with respect to the Post aggregate — nothing is persisted until
 * the user reviews the draft and submits the normal store/update route.
 * Gated by `permission:CREATE_POSTS` + a tight `throttle:` (real API cost per
 * call) — see Routes. Each handler meta-audits the acting user since every
 * call is a real, billed provider request.
 */
final readonly class PostAiAssistController
{
    public function __construct(
        private SuggestPostTopicsHandler $suggestTopics,
        private GeneratePostContentHandler $generateContent,
        private GenerateSocialCopyHandler $generateSocialCopy,
        private GenerateReelPackageHandler $generateReel,
    ) {}

    public function suggestTopics(SuggestPostTopicsData $data, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->suggestTopics->handle($data, $request->user())]);
    }

    public function generateContent(GeneratePostContentData $data, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->generateContent->handle($data, $request->user())]);
    }

    public function generateSocialCopy(GenerateContentVariantData $data, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->generateSocialCopy->handle($data, $request->user())]);
    }

    public function generateReel(GenerateContentVariantData $data, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->generateReel->handle($data, $request->user())]);
    }
}
