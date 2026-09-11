<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
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
 * under the `sanctum` guard. Responses are documented by Scramble from the
 * return types.
 *
 * Filters are documented from the injected {@see SocialMediaContentFilterData}:
 * Scramble reads a `Data` parameter's rules directly, while the equivalent
 * `SocialMediaContentFilterData::validateAndCreate($request)` call hides them —
 * the rules live in a static method the analyser cannot follow, so this endpoint
 * used to document `per_page` and nothing else. Injection validates identically
 * and is what the web controllers already do.
 */
final readonly class SocialMediaApiController
{
    /**
     * List content packages.
     *
     * Returns a paginated list. `per_page` is capped at 100 to bound resource
     * consumption (OWASP API4).
     *
     * `search` matches the content topic or headline.
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        SocialMediaContentFilterData $filters,
        ListSocialMediaContentHandler $list,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_SOCIAL_MEDIA'), 403);

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
