<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Post\Application\Commands\GenerateReelPackageHandler;
use Modules\Post\Application\Commands\GenerateSocialCopyHandler;
use Modules\Post\Application\Commands\StartPostContentGenerationHandler;
use Modules\Post\Application\Commands\SuggestPostTopicsHandler;
use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;
use Modules\Post\Application\Queries\GetPostAiGenerationHandler;
use Modules\Post\Infrastructure\Queue\GeneratePostContentJob;

/**
 * XHR-only AI actions consumed by the Create/Edit AI-assist panel. All are
 * read-only with respect to the Post aggregate — nothing is persisted to a
 * post until the user reviews the draft and submits the normal store/update
 * route. Gated by `permission:CREATE_POSTS` + a tight `throttle:` (real API
 * cost per call) — see Routes. Each handler meta-audits the acting user since
 * every call is a real, billed provider request.
 *
 * Topics, social copy and the reel package are single provider calls and stay
 * synchronous. `generateContent` is not: it answers `202` with a `queued`
 * generation row and hands the up-to-5-iteration quality loop to
 * {@see GeneratePostContentJob}. The panel
 * polls {@see self::generationStatus()} for the phases and the finished draft
 * rather than holding a request open for minutes.
 */
final readonly class PostAiAssistController
{
    public function __construct(
        private SuggestPostTopicsHandler $suggestTopics,
        private StartPostContentGenerationHandler $startGeneration,
        private GetPostAiGenerationHandler $getGeneration,
        private GenerateSocialCopyHandler $generateSocialCopy,
        private GenerateReelPackageHandler $generateReel,
    ) {}

    public function suggestTopics(SuggestPostTopicsData $data, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->suggestTopics->handle($data, $request->user())]);
    }

    public function generateContent(GeneratePostContentData $data, Request $request): JsonResponse
    {
        return response()->json(
            ['data' => $this->startGeneration->handle($data, $request->user())],
            202,
        );
    }

    public function generationStatus(string $uuid, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->getGeneration->handle($uuid, $request->user())]);
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
