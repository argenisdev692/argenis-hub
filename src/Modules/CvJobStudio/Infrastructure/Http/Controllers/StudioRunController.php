<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Commands\StartRunHandler;
use Modules\CvJobStudio\Application\Queries\GetInsightReportHandler;
use Modules\CvJobStudio\Application\Queries\GetRunHandler;

/** Runs: start (queued), per-stage progress, insight report (T-043, T-085). */
final readonly class StudioRunController
{
    public function store(Request $request, StartRunHandler $start): JsonResponse
    {
        /** @var array{profile_uuid: string} $validated */
        $validated = $request->validate(['profile_uuid' => ['required', 'string', 'uuid']]);

        $run = $start->handle($validated['profile_uuid'], $this->ownerId($request));

        return response()->json(['data' => ['uuid' => $run->uuid, 'status' => $run->status]], 202);
    }

    public function show(Request $request, string $uuid, GetRunHandler $get): InertiaResponse|JsonResponse
    {
        $run = $get->handle($uuid, $this->ownerId($request));

        $props = ['run' => $run];

        return match ($request->expectsJson()) {
            true => response()->json($props),
            false => Inertia::render('cv-studio/Runs/Show', $props),
        };
    }

    public function report(Request $request, string $uuid, GetInsightReportHandler $get): InertiaResponse|JsonResponse
    {
        $report = $get->handle($uuid, $this->ownerId($request));

        $props = ['report' => $report];

        return match ($request->expectsJson()) {
            true => response()->json($props),
            false => Inertia::render('cv-studio/Insights/Show', $props),
        };
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
