<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Availability\Application\Commands\BulkDeleteAvailabilityExceptionsHandler;
use Modules\Availability\Application\Commands\BulkRestoreAvailabilityExceptionsHandler;
use Modules\Availability\Application\Commands\CreateAvailabilityExceptionHandler;
use Modules\Availability\Application\Commands\DeleteAvailabilityExceptionHandler;
use Modules\Availability\Application\Commands\RestoreAvailabilityExceptionHandler;
use Modules\Availability\Application\Commands\UpdateAvailabilityExceptionHandler;
use Modules\Availability\Application\DTOs\AvailabilityExceptionData;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Application\Queries\GetAvailabilityExceptionHandler;
use Modules\Availability\Application\Queries\ListAvailabilityExceptionsHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Date exceptions (closures / forced-open overrides). Every route is authorized
 * via `permission:*_AVAILABILITY_EXCEPTIONS` middleware (never roles). Controller
 * stays thin: validate → handler → response, branching Inertia vs JSON.
 */
final readonly class AvailabilityExceptionController
{
    public function index(Request $request, ListAvailabilityExceptionsHandler $list): InertiaResponse|JsonResponse
    {
        $filters = AvailabilityExceptionFilterData::validateAndCreate($request);
        $exceptions = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return match ($request->expectsJson()) {
            true => response()->json($exceptions),
            false => Inertia::render('availability-exceptions/Index', ['availabilityExceptions' => $exceptions, 'filters' => $filters]),
        };
    }

    public function show(Request $request, string $uuid, GetAvailabilityExceptionHandler $get): InertiaResponse|JsonResponse
    {
        $exception = $get->handle($uuid);

        return match ($request->expectsJson()) {
            true => response()->json(['data' => $exception]),
            false => Inertia::render('availability-exceptions/Show', ['availabilityException' => $exception]),
        };
    }

    public function store(AvailabilityExceptionData $data, CreateAvailabilityExceptionHandler $create): RedirectResponse
    {
        (void) $create->handle($data);

        return back()->with('success', __('Availability exception created.'));
    }

    public function update(string $uuid, AvailabilityExceptionData $data, GetAvailabilityExceptionHandler $get, UpdateAvailabilityExceptionHandler $update): RedirectResponse
    {
        (void) $update->handle($get->handle($uuid), $data);

        return back()->with('success', __('Availability exception updated.'));
    }

    public function destroy(string $uuid, DeleteAvailabilityExceptionHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid);

        return back()->with('success', __('Availability exception suspended.'));
    }

    public function restore(string $uuid, RestoreAvailabilityExceptionHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Availability exception restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeleteAvailabilityExceptionsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count availability exceptions suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestoreAvailabilityExceptionsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count availability exceptions restored.', ['count' => $count]));
    }
}
