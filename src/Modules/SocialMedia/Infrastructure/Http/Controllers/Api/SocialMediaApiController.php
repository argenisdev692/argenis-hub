<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Post\Infrastructure\Http\Controllers\Api\PostApiController;
use Modules\SocialMedia\Application\Commands\GenerateSocialMediaContentHandler;
use Modules\SocialMedia\Application\Commands\SuggestSocialMediaTopicsHandler;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;
use Modules\SocialMedia\Application\DTOs\SuggestSocialMediaTopicsData;
use Modules\SocialMedia\Application\Queries\GetSocialMediaContentHandler;
use Modules\SocialMedia\Application\Queries\ListSocialMediaContentHandler;

/**
 * API endpoints for content lookup + the 2-step AI wizard. Secondary
 * Sanctum-authenticated surface (mobile/external clients); the primary UI
 * remains Inertia/web — mirrors {@see PostApiController}.
 * Authorization is checked on the model (`hasPermissionTo`) so it is safe
 * under the `sanctum` guard. Documented by Scramble via return types +
 * `auth:sanctum` detection — no manual annotations.
 */
final readonly class SocialMediaApiController
{
    /**
     * List content packages.
     *
     * Returns a paginated list. `per_page` is capped at 100 to bound resource
     * consumption (OWASP API4).
     */
    public function index(Request $request, ListSocialMediaContentHandler $list): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_SOCIAL_MEDIA'), 403);

        $filters = SocialMediaContentFilterData::validateAndCreate($request);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show a content package.
     *
     * Returns a single package by UUID, including per-platform copy and scores.
     */
    public function show(Request $request, string $uuid, GetSocialMediaContentHandler $get): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_SOCIAL_MEDIA'), 403);

        return response()->json(['data' => $get->handle($uuid)]);
    }

    /**
     * Suggest AI viral topics.
     *
     * Returns exactly 10 candidate topics classified by TOFU/MOFU/BOFU funnel
     * stage, grounded in the company profile and current web trends. Real,
     * billed provider request.
     */
    public function suggestTopics(SuggestSocialMediaTopicsData $data, Request $request, SuggestSocialMediaTopicsHandler $suggestTopics): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_SOCIAL_MEDIA'), 403);

        return response()->json(['data' => $suggestTopics->handle($data, $request->user())]);
    }

    /**
     * Generate a social media content package.
     *
     * Returns immediately with a `generating` row and kicks off the
     * up-to-5-iteration quality loop in the background — poll `show()` or
     * subscribe to `social-media.ai.progress` for completion. Real, billed
     * provider request.
     */
    public function generateContent(GenerateSocialMediaContentData $data, Request $request, GenerateSocialMediaContentHandler $generateContent): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_SOCIAL_MEDIA'), 403);

        return response()->json(['data' => $generateContent->handle($data, $request->user())], 202);
    }
}
