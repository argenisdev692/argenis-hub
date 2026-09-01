<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Campaigns\Application\Commands\BulkDeleteCampaignsHandler;
use Modules\Campaigns\Application\Commands\BulkRestoreCampaignsHandler;
use Modules\Campaigns\Application\Commands\DeleteCampaignHandler;
use Modules\Campaigns\Application\Commands\PublishCampaignHandler;
use Modules\Campaigns\Application\Commands\RestoreCampaignHandler;
use Modules\Campaigns\Application\Commands\UpdateCampaignHandler;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Campaigns\Application\DTOs\UpdateCampaignData;
use Modules\Campaigns\Application\Queries\GetCampaignHandler;
use Modules\Campaigns\Application\Queries\ListCampaignsHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Campaign management. Every route is authorized via `permission:*_CAMPAIGNS`
 * middleware (not roles) — see Routes. There is no `create`/`store`: a
 * campaign is always AI-born via
 * {@see CampaignAiAssistController} —
 * this controller only reviews, edits, publishes, and manages the lifecycle
 * of an already-generated campaign.
 */
final readonly class CampaignController
{
    public function index(Request $request, ListCampaignsHandler $list): InertiaResponse|JsonResponse
    {
        $filters = CampaignFilterData::validateAndCreate($request);
        $campaigns = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return match ($request->expectsJson()) {
            true => response()->json($campaigns),
            false => Inertia::render('campaigns/Index', [
                'campaigns' => $campaigns,
                'filters' => $filters,
            ]),
        };
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('campaigns/Create');
    }

    public function edit(string $uuid, GetCampaignHandler $get): InertiaResponse
    {
        return Inertia::render('campaigns/Edit', [
            'campaign' => $get->handle($uuid),
        ]);
    }

    public function update(
        string $uuid,
        UpdateCampaignData $data,
        GetCampaignHandler $get,
        UpdateCampaignHandler $update,
    ): RedirectResponse {
        (void) $update->handle($get->handle($uuid), $data);

        return redirect()->route('campaigns.index')->with('success', __('Campaign updated.'));
    }

    public function publish(string $uuid, Request $request, GetCampaignHandler $get, PublishCampaignHandler $publish): RedirectResponse
    {
        (void) $publish->handle($get->handle($uuid), $request->user());

        return back()->with('success', __('Campaign marked as published.'));
    }

    public function destroy(string $uuid, DeleteCampaignHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid);

        return back()->with('success', __('Campaign suspended.'));
    }

    public function restore(string $uuid, RestoreCampaignHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Campaign restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeleteCampaignsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count campaigns suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestoreCampaignsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count campaigns restored.', ['count' => $count]));
    }
}
