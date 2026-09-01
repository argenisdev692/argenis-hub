<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Post\Application\Commands\GenerateReelPackageHandler;
use Modules\Post\Application\Commands\GenerateSocialCopyHandler;
use Modules\Post\Application\Commands\StartPostContentGenerationHandler;
use Modules\Post\Application\Commands\SuggestPostTopicsHandler;
use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostFilterData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;
use Modules\Post\Application\Queries\GetPostAiGenerationHandler;
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
     * Returns the 10 most viral candidate blog topics for the blog category
     * given in `category_uuid` (required — the category is the niche), ranked
     * with virality / ROI / EEAT estimates and grounded in current web trends.
     * An optional `topic` narrows the angle inside that category. Real, billed
     * provider request.
     */
    public function suggestTopics(SuggestPostTopicsData $data, Request $request, SuggestPostTopicsHandler $suggestTopics): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_POSTS'), 403);

        return response()->json(['data' => $suggestTopics->handle($data, $request->user())]);
    }

    /**
     * Start an AI blog draft generation.
     *
     * Answers `202` immediately with a `queued` generation record and runs the
     * up-to-5-iteration quality loop in the background — poll
     * `GET /api/posts/ai/generations/{uuid}` until `is_terminal` is true, then
     * read `result` for the finished draft.
     *
     * The `image_mode` field controls the cover artwork: `full` renders the
     * complete brand-palette composite, `base` renders only the palette
     * background plate to composite on yourself, and `none` bills no image
     * call. The layered image prompts are returned in every mode. Real, billed
     * provider request.
     */
    public function generateContent(GeneratePostContentData $data, Request $request, StartPostContentGenerationHandler $startGeneration): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_POSTS'), 403);

        return response()->json(['data' => $startGeneration->handle($data, $request->user())], 202);
    }

    /**
     * Poll an AI blog draft generation.
     *
     * Reports the live phase (`researching`, `writing`, `judging`,
     * `generating_image`) and, once `status` is `completed`, the finished
     * draft in `result`. A generation started by another user is reported as
     * not found.
     */
    public function generationStatus(string $uuid, Request $request, GetPostAiGenerationHandler $getGeneration): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_POSTS'), 403);

        return response()->json(['data' => $getGeneration->handle($uuid, $request->user())]);
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
