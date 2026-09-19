<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\CvJobStudio\Application\Commands\ConfirmRelationHandler;
use Modules\CvJobStudio\Application\Commands\RejectRelationHandler;
use Modules\CvJobStudio\Application\Queries\ListPendingRelationsHandler;

/** Relations inbox (T-124, T-135): confirm / reject pending proposals. */
final readonly class StudioRelationController
{
    public function index(Request $request, ListPendingRelationsHandler $list): JsonResponse
    {
        return response()->json($list->handle($this->ownerId($request)));
    }

    public function confirm(Request $request, string $uuid, ConfirmRelationHandler $handler): RedirectResponse
    {
        (void) $handler->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Relation confirmed.'));
    }

    public function reject(Request $request, string $uuid, RejectRelationHandler $handler): RedirectResponse
    {
        (void) $handler->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Relation rejected.'));
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
