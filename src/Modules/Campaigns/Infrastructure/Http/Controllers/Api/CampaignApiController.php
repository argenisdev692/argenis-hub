<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Campaigns\Application\Commands\GenerateCampaignHandler;
use Modules\Campaigns\Application\Commands\SuggestCampaignTopicsHandler;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Application\DTOs\SuggestCampaignTopicsData;
use Modules\Campaigns\Application\Queries\GetCampaignHandler;
use Modules\Campaigns\Application\Queries\ListCampaignsHandler;
use Modules\SocialMedia\Infrastructure\Http\Controllers\Api\SocialMediaApiController;

/**
 * API endpoints for campaign lookup + the 2-step AI wizard. Secondary
 * Sanctum-authenticated surface (mobile/external clients); the primary UI
 * remains Inertia/web — mirrors {@see SocialMediaApiController}.
 * Authorization is checked on the model (`hasPermissionTo`) so it is safe
 * under the `sanctum` guard. Responses are documented by Scramble from the
 * return types.
 *
 * Filters are documented from the injected {@see CampaignFilterData}: Scramble
 * reads a `Data` parameter's rules directly, while the equivalent
 * `CampaignFilterData::validateAndCreate($request)` call hides them — the rules
 * live in a static method the analyser cannot follow, so this endpoint used to
 * document `per_page` and nothing else. Injection validates identically and is
 * what the web controllers already do.
 */
final readonly class CampaignApiController
{
    /**
     * List campaigns.
     *
     * Returns a paginated list. `per_page` is capped at 100 to bound resource
     * consumption (OWASP API4).
     *
     * `search` matches the campaign topic or headline.
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        CampaignFilterData $filters,
        ListCampaignsHandler $list,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_CAMPAIGNS'), 403);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show a campaign.
     *
     * Returns a single campaign by UUID, including per-platform Meta Ads copy
     * and success-probability scores.
     */
    public function show(Request $request, string $uuid, GetCampaignHandler $get): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_CAMPAIGNS'), 403);

        return response()->json(['data' => $get->handle($uuid)]);
    }

    /**
     * Suggest AI campaign angles.
     *
     * Returns exactly 10 candidate Meta Ads lead-gen angles classified by
     * TOFU/MOFU/BOFU/LOYALTY funnel stage, grounded in the company profile
     * and current web trends. Real, billed provider request.
     */
    public function suggestTopics(SuggestCampaignTopicsData $data, Request $request, SuggestCampaignTopicsHandler $suggestTopics): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_CAMPAIGNS'), 403);

        return response()->json(['data' => $suggestTopics->handle($data, $request->user())]);
    }

    /**
     * Generate a Meta Ads campaign.
     *
     * Returns immediately with a `generating` row and kicks off the
     * up-to-5-iteration quality loop in the background — poll `show()` or
     * subscribe to `campaigns.ai.progress` for completion. Real, billed
     * provider request.
     */
    public function generateCampaign(GenerateCampaignData $data, Request $request, GenerateCampaignHandler $generateCampaign): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('CREATE_CAMPAIGNS'), 403);

        return response()->json(['data' => $generateCampaign->handle($data, $request->user())], 202);
    }
}
