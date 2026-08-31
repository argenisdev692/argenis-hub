<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\SocialMedia\Application\Commands\BulkDeleteSocialMediaContentHandler;
use Modules\SocialMedia\Application\Commands\BulkRestoreSocialMediaContentHandler;
use Modules\SocialMedia\Application\Commands\DeleteSocialMediaContentHandler;
use Modules\SocialMedia\Application\Commands\PublishSocialMediaContentHandler;
use Modules\SocialMedia\Application\Commands\RestoreSocialMediaContentHandler;
use Modules\SocialMedia\Application\Commands\UpdateSocialMediaContentHandler;
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;
use Modules\SocialMedia\Application\DTOs\UpdateSocialMediaContentData;
use Modules\SocialMedia\Application\Queries\GetSocialMediaContentHandler;
use Modules\SocialMedia\Application\Queries\ListSocialMediaContentHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Social media content management. Every route is authorized via
 * `permission:*_SOCIAL_MEDIA` middleware (not roles) — see Routes. There is no
 * `create`/`store`: content is always AI-born via
 * {@see SocialMediaAiAssistController} —
 * this controller only reviews, edits, publishes, and manages the lifecycle
 * of an already-generated package.
 */
final readonly class SocialMediaContentController
{
    public function index(Request $request, ListSocialMediaContentHandler $list): InertiaResponse|JsonResponse
    {
        $filters = SocialMediaContentFilterData::validateAndCreate($request);
        $content = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return match ($request->expectsJson()) {
            true => response()->json($content),
            false => Inertia::render('social-media/Index', [
                'content' => $content,
                'filters' => $filters,
            ]),
        };
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('social-media/Create');
    }

    public function edit(string $uuid, GetSocialMediaContentHandler $get): InertiaResponse
    {
        return Inertia::render('social-media/Edit', [
            'content' => $get->handle($uuid),
        ]);
    }

    public function update(
        string $uuid,
        UpdateSocialMediaContentData $data,
        GetSocialMediaContentHandler $get,
        UpdateSocialMediaContentHandler $update,
    ): RedirectResponse {
        (void) $update->handle($get->handle($uuid), $data);

        return redirect()->route('social-media.index')->with('success', __('Content updated.'));
    }

    public function publish(string $uuid, Request $request, GetSocialMediaContentHandler $get, PublishSocialMediaContentHandler $publish): RedirectResponse
    {
        (void) $publish->handle($get->handle($uuid), $request->user());

        return back()->with('success', __('Content marked as published.'));
    }

    public function destroy(string $uuid, DeleteSocialMediaContentHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid);

        return back()->with('success', __('Content suspended.'));
    }

    public function restore(string $uuid, RestoreSocialMediaContentHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Content restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeleteSocialMediaContentHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count content packages suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestoreSocialMediaContentHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count content packages restored.', ['count' => $count]));
    }
}
