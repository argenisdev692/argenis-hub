<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Queries\GetBudgetStatusHandler;
use Modules\CvJobStudio\Application\Queries\GetOwnRatesHandler;
use Modules\CvJobStudio\Application\Queries\ListSourcesHandler;

/** Budgets (spend vs limit) + source catalogue with health (T-098, FR-32). */
final readonly class StudioCatalogController
{
    public function budgets(Request $request, GetBudgetStatusHandler $budgets): JsonResponse
    {
        return response()->json(['data' => $budgets->handle($this->ownerId($request))]);
    }

    public function ownRates(Request $request, GetOwnRatesHandler $rates): JsonResponse
    {
        return response()->json(['data' => $rates->handle($this->ownerId($request))]);
    }

    public function dashboard(): InertiaResponse
    {
        return Inertia::render('cv-studio/Dashboard');
    }

    public function sources(Request $request, ListSourcesHandler $list): JsonResponse
    {
        return response()->json(['data' => $list->handle($this->ownerId($request))]);
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
