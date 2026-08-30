<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Application\Commands\GenerateReelPackageHandler;
use Modules\Post\Application\Commands\GenerateSocialCopyHandler;
use Modules\Post\Application\Commands\SuggestPostTopicsHandler;
use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;
use Modules\Post\Application\Queries\GetPostHandler;
use Modules\Post\Application\Queries\ListPostsHandler;
use Modules\Post\Infrastructure\Http\Controllers\PostAiAssistController;

/**
 * API endpoints for post lookup + AI-assist. Secondary Sanctum-authenticated
 * surface (mobile clients); the primary UI remains Inertia/web. AI-assist
 * methods reuse the same handlers as {@see PostAiAssistController}
 * — authorization is checked on the model (`hasPermissionTo`) so it is safe
 * under the `sanctum` guard. Documented by Scramble via return types +
 * `auth:sanctum` detection — no manual annotations.
 */
final readonly class PostApiController
{
    /**
     * List posts.
     *
     * Returns a paginated list of posts. `per_page` is capped at 100 to bound
     * resource consumption (OWASP API4).
     */
    public function index(Request $request, ListPostsHandler $list): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_POSTS'), 403);

        $filters = PostFilterData::validateAndCreate($request);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show a post.
     *
     * Returns a single post by UUID.
     */
    public function show(Request $request, string $uuid, GetPostHandler $get): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_POSTS'), 403);

        return response()->json(['data' => $get->handle($uuid)]);
    }

    /**
     * Suggest AI topic ideas.
     *
     * Returns up to 10 candidate blog topics grounded in the company profile
     * and current web trends. Real, billed provider request.
     */
    public function suggestTopics(SuggestPostTopicsData $data, Request $request, SuggestPostTopicsHandler $suggestTopics): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_POSTS'), 403);

        return response()->json(['data' => $suggestTopics->handle($data, $request->user())]);
    }

    /**
     * Generate an AI blog draft.
     *
     * Returns a full SEO/EEAT-scored draft for a chosen topic/angle, with an
     * optional on-brand cover image. Real, billed provider request.
     */
    public function generateContent(GeneratePostContentData $data, Request $request, GeneratePostContentHandler $generateContent): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_POSTS'), 403);

        return response()->json(['data' => $generateContent->handle($data, $request->user())]);
    }

    /**
     * Generate AI social copy.
     *
     * Returns a LinkedIn post + shared Instagram/Facebook caption + hashtags
     * for a chosen topic/angle. Real, billed provider request.
     */
    public function generateSocialCopy(GenerateContentVariantData $data, Request $request, GenerateSocialCopyHandler $generateSocialCopy): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_POSTS'), 403);

        return response()->json(['data' => $generateSocialCopy->handle($data, $request->user())]);
    }

    /**
     * Generate an AI Reel/TikTok package.
     *
     * Returns a scene timeline, clean script, sound cue, TikTok caption/hashtags
     * and an AI voiceover (when synthesis succeeds). Real, billed provider request.
     */
    public function generateReel(GenerateContentVariantData $data, Request $request, GenerateReelPackageHandler $generateReel): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_POSTS'), 403);

        return response()->json(['data' => $generateReel->handle($data, $request->user())]);
    }
}
