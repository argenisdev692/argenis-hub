<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Commands\BulkDeletePostingsHandler;
use Modules\CvJobStudio\Application\Commands\BulkRestorePostingsHandler;
use Modules\CvJobStudio\Application\Commands\DeletePostingHandler;
use Modules\CvJobStudio\Application\Commands\DismissRequirementHandler;
use Modules\CvJobStudio\Application\Commands\IngestPostingHandler;
use Modules\CvJobStudio\Application\Commands\RestorePostingHandler;
use Modules\CvJobStudio\Application\Commands\ScorePostingHandler;
use Modules\CvJobStudio\Application\DTOs\IngestPostingData;
use Modules\CvJobStudio\Application\DTOs\ScorePostingInputData;
use Modules\CvJobStudio\Application\DTOs\StudioPostingData;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Modules\CvJobStudio\Application\DTOs\StudioScoreData;
use Modules\CvJobStudio\Application\Queries\GetPostingHandler;
use Modules\CvJobStudio\Application\Queries\ListPostingsHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Posting intake, scoring and lifecycle. Authorization via
 * `permission:*_STUDIO_POSTINGS` middleware, ownership via the `$userId`
 * every handler requires (OWASP §11). Thin: validate → handler → response.
 */
final readonly class StudioPostingController
{
    public function index(Request $request, StudioPostingFilterData $filters, ListPostingsHandler $list): InertiaResponse|JsonResponse
    {
        $postings = $list->handle(
            $filters,
            $this->ownerId($request),
            min(max($request->integer('per_page', 15), 1), 100),
        );

        $props = [
            'postings' => StudioPostingData::collect($postings),
            'filters' => $filters,
        ];

        return match ($request->expectsJson()) {
            true => response()->json($props['postings']),
            false => Inertia::render('cv-studio/Postings/Index', $props),
        };
    }

    public function show(Request $request, string $uuid, GetPostingHandler $get): InertiaResponse|JsonResponse
    {
        $posting = $get->handle($uuid, $this->ownerId($request));
        $data = StudioPostingData::fromModel($posting);

        // Match-report props: the stored breakdown is sufficient to explain
        // the total without recomputing (SC-2). Scores eager-load matches +
        // requirement names through the repository's scoring context.
        $latest = $posting->relationLoaded('scores') ? $posting->scores->first() : null;

        $props = [
            'posting' => $data,
            'requirements' => $posting->relationLoaded('requirements')
                ? $posting->requirements->map(static fn ($requirement): array => [
                    'uuid' => $requirement->uuid,
                    'canonical_name' => $requirement->canonical_name,
                    'tag' => $requirement->tag->value,
                    'nature' => $requirement->nature->value,
                ])->all()
                : [],
            'gates' => $posting->relationLoaded('gateResults')
                ? $posting->gateResults->map(static fn ($gate): array => [
                    'gate_code' => $gate->gate_code,
                    'passed' => $gate->passed,
                    'reason_code' => $gate->reason_code,
                ])->all()
                : [],
            'score' => $latest !== null ? StudioScoreData::fromModel($latest) : null,
        ];

        return match ($request->expectsJson()) {
            true => response()->json($props),
            false => Inertia::render('cv-studio/Postings/Show', $props),
        };
    }

    public function store(Request $request, IngestPostingData $data, IngestPostingHandler $ingest): RedirectResponse
    {
        (void) $ingest->handle($data, $this->ownerId($request));

        return back()->with('success', __('Posting ingested.'));
    }

    public function score(Request $request, string $uuid, ScorePostingInputData $input, ScorePostingHandler $score): JsonResponse
    {
        $result = $score->handle($uuid, $input, $this->ownerId($request));

        return response()->json(['data' => StudioScoreData::fromModel($result)], 201);
    }

    public function rescore(Request $request, string $uuid, ScorePostingInputData $input, ScorePostingHandler $score): JsonResponse
    {
        $result = $score->handle($uuid, $input, $this->ownerId($request));

        return response()->json(['data' => StudioScoreData::fromModel($result)], 200);
    }

    public function destroy(Request $request, string $uuid, DeletePostingHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Posting suspended.'));
    }

    public function restore(Request $request, string $uuid, RestorePostingHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Posting restored.'));
    }

    public function bulkDelete(Request $request, BulkUuidsData $data, BulkDeletePostingsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data, $this->ownerId($request));

        return back()->with('success', __(':count postings suspended.', ['count' => $count]));
    }

    public function bulkRestore(Request $request, BulkUuidsData $data, BulkRestorePostingsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data, $this->ownerId($request));

        return back()->with('success', __(':count postings restored.', ['count' => $count]));
    }

    public function dismissRequirement(Request $request, string $uuid, string $ruuid, DismissRequirementHandler $handler): RedirectResponse
    {
        (void) $handler->handle($ruuid, $this->ownerId($request));

        return back()->with('success', __('Requirement dismissed — rescore to apply.'));
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
