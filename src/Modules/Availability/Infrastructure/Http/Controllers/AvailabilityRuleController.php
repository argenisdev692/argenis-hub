<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Availability\Application\Commands\BulkDeleteAvailabilityRulesHandler;
use Modules\Availability\Application\Commands\BulkRestoreAvailabilityRulesHandler;
use Modules\Availability\Application\Commands\CreateAvailabilityRuleHandler;
use Modules\Availability\Application\Commands\DeleteAvailabilityRuleHandler;
use Modules\Availability\Application\Commands\RestoreAvailabilityRuleHandler;
use Modules\Availability\Application\Commands\UpdateAvailabilityRuleHandler;
use Modules\Availability\Application\DTOs\AvailabilityRuleData;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Application\Queries\GetAvailabilityRuleHandler;
use Modules\Availability\Application\Queries\ListAvailabilityRulesHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Weekly availability rules (the recurring template). Every route is authorized
 * via `permission:*_AVAILABILITY_RULES` middleware (never roles). Controller stays
 * thin: validate → handler → response, branching Inertia vs JSON.
 */
final readonly class AvailabilityRuleController
{
    public function index(Request $request, ListAvailabilityRulesHandler $list): InertiaResponse|JsonResponse
    {
        $filters = AvailabilityRuleFilterData::validateAndCreate($request);
        $rules = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return match ($request->expectsJson()) {
            true => response()->json($rules),
            false => Inertia::render('availability-rules/Index', ['availabilityRules' => $rules, 'filters' => $filters]),
        };
    }

    public function show(Request $request, string $uuid, GetAvailabilityRuleHandler $get): InertiaResponse|JsonResponse
    {
        $rule = $get->handle($uuid);

        return match ($request->expectsJson()) {
            true => response()->json(['data' => $rule]),
            false => Inertia::render('availability-rules/Show', ['availabilityRule' => $rule]),
        };
    }

    public function store(AvailabilityRuleData $data, CreateAvailabilityRuleHandler $create): RedirectResponse
    {
        (void) $create->handle($data);

        return back()->with('success', __('Availability rule created.'));
    }

    public function update(string $uuid, AvailabilityRuleData $data, GetAvailabilityRuleHandler $get, UpdateAvailabilityRuleHandler $update): RedirectResponse
    {
        (void) $update->handle($get->handle($uuid), $data);

        return back()->with('success', __('Availability rule updated.'));
    }

    public function destroy(string $uuid, DeleteAvailabilityRuleHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid);

        return back()->with('success', __('Availability rule suspended.'));
    }

    public function restore(string $uuid, RestoreAvailabilityRuleHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Availability rule restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeleteAvailabilityRulesHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count availability rules suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestoreAvailabilityRulesHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count availability rules restored.', ['count' => $count]));
    }
}
