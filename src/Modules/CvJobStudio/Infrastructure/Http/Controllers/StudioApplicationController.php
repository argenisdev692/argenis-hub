<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Commands\RecordOutcomeHandler;
use Modules\CvJobStudio\Application\Commands\UpdatePostingStatusHandler;
use Modules\CvJobStudio\Application\DTOs\RecordOutcomeData;
use Modules\CvJobStudio\Application\DTOs\UpdatePostingStatusData;
use Modules\CvJobStudio\Application\Queries\ListApplicationsHandler;

/** Candidate-side status + employer-side outcome, separately (T-080, FR-25). */
final readonly class StudioApplicationController
{
    public function index(Request $request, ListApplicationsHandler $list): InertiaResponse|JsonResponse
    {
        $applications = $list->handle($this->ownerId($request), $request->integer('per_page', 15));

        return match ($request->expectsJson()) {
            true => response()->json($applications),
            false => Inertia::render('cv-studio/Applications/Board', ['applications' => $applications]),
        };
    }

    /** JSON for the postings table's row menu (`fetch`), a flash redirect for Inertia. */
    public function updateStatus(Request $request, string $uuid, UpdatePostingStatusData $data, UpdatePostingStatusHandler $handler): RedirectResponse|JsonResponse
    {
        (void) $handler->handle($uuid, $data->status, $this->ownerId($request));

        return $request->expectsJson()
            ? response()->json(['message' => __('Posting status updated.'), 'status' => $data->status])
            : back()->with('success', __('Posting status updated.'));
    }

    public function recordOutcome(Request $request, string $uuid, RecordOutcomeData $data, RecordOutcomeHandler $handler): RedirectResponse
    {
        (void) $handler->handle($uuid, $data->outcome, $data->note, $this->ownerId($request));

        return back()->with('success', __('Outcome recorded.'));
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
