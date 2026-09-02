<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Cvs\Application\Commands\BulkDeleteCvsHandler;
use Modules\Cvs\Application\Commands\BulkRestoreCvsHandler;
use Modules\Cvs\Application\Commands\CreateCvHandler;
use Modules\Cvs\Application\Commands\DeleteCvHandler;
use Modules\Cvs\Application\Commands\RestoreCvHandler;
use Modules\Cvs\Application\Commands\UpdateCvHandler;
use Modules\Cvs\Application\DTOs\CvData;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Application\DTOs\UploadCvData;
use Modules\Cvs\Application\Queries\GetCvHandler;
use Modules\Cvs\Application\Queries\ListCvsHandler;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Ports\StoragePort;

/**
 * CV upload management. Authorization via `permission:*_CVS` middleware, and
 * ownership via the `$userId` every handler requires (OWASP §11).
 * Thin: validate → handler → Inertia or JSON.
 */
final readonly class CvController
{
    private const int DOWNLOAD_URL_TTL_MINUTES = 15;

    public function index(Request $request, ListCvsHandler $list): InertiaResponse|JsonResponse
    {
        $filters = CvFilterData::validateAndCreate($request);
        $cvs = $list->handle(
            $filters,
            $this->ownerId($request),
            min(max($request->integer('per_page', 15), 1), 100),
        );

        $props = [
            'cvs' => CvData::collect($cvs),
            'filters' => $filters,
        ];

        return match ($request->expectsJson()) {
            true => response()->json($props['cvs']),
            false => Inertia::render('cvs/Index', $props),
        };
    }

    public function show(Request $request, string $uuid, GetCvHandler $get, StoragePort $storage): InertiaResponse|JsonResponse
    {
        $cv = $get->handle($uuid, $this->ownerId($request));
        $data = CvData::fromModel(
            $cv,
            $storage->temporaryUrl($cv->file_path, now()->addMinutes(self::DOWNLOAD_URL_TTL_MINUTES)),
        );

        return match ($request->expectsJson()) {
            true => response()->json(['data' => $data]),
            false => Inertia::render('cvs/Show', ['cv' => $data]),
        };
    }

    public function store(Request $request, UploadCvData $data, CreateCvHandler $create): RedirectResponse
    {
        (void) $create->handle($data, $this->ownerId($request));

        return back()->with('success', __('CV uploaded.'));
    }

    public function update(Request $request, string $uuid, UploadCvData $data, GetCvHandler $get, UpdateCvHandler $update): RedirectResponse
    {
        (void) $update->handle($get->handle($uuid, $this->ownerId($request)), $data);

        return back()->with('success', __('CV updated.'));
    }

    public function destroy(Request $request, string $uuid, DeleteCvHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('CV suspended.'));
    }

    public function restore(Request $request, string $uuid, RestoreCvHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('CV restored.'));
    }

    public function bulkDelete(Request $request, BulkUuidsData $data, BulkDeleteCvsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data, $this->ownerId($request));

        return back()->with('success', __(':count CVs suspended.', ['count' => $count]));
    }

    public function bulkRestore(Request $request, BulkUuidsData $data, BulkRestoreCvsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data, $this->ownerId($request));

        return back()->with('success', __(':count CVs restored.', ['count' => $count]));
    }

    /**
     * The authenticated owner every handler scopes to. The `auth` middleware
     * guarantees a user is present on every route in this group.
     */
    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
