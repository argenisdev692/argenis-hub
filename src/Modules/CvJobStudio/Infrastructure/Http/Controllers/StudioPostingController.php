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
    /**
     * The page shell renders prop-less: the table is Pinia Colada server state
     * and fetches this same route as JSON, so paginating here for Inertia
     * would run the query twice and discard the first result.
     */
    public function index(Request $request, StudioPostingFilterData $filters, ListPostingsHandler $list): InertiaResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return Inertia::render('cv-studio/Postings/Index');
        }

        $postings = $list->handle(
            $filters,
            $this->ownerId($request),
            min(max($request->integer('per_page', 15), 1), 100),
        );

        return response()->json(StudioPostingData::collect($postings));
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

    public function destroy(Request $request, string $uuid, DeletePostingHandler $delete): RedirectResponse|JsonResponse
    {
        (void) $delete->handle($uuid, $this->ownerId($request));

        return $this->respond($request, __('Posting suspended.'));
    }

    public function restore(Request $request, string $uuid, RestorePostingHandler $restore): RedirectResponse|JsonResponse
    {
        (void) $restore->handle($uuid, $this->ownerId($request));

        return $this->respond($request, __('Posting restored.'));
    }

    public function bulkDelete(Request $request, BulkUuidsData $data, BulkDeletePostingsHandler $handler): RedirectResponse|JsonResponse
    {
        $count = $handler->handle($data, $this->ownerId($request));

        return $this->respond($request, __(':count postings suspended.', ['count' => $count]), ['count' => $count]);
    }

    public function bulkRestore(Request $request, BulkUuidsData $data, BulkRestorePostingsHandler $handler): RedirectResponse|JsonResponse
    {
        $count = $handler->handle($data, $this->ownerId($request));

        return $this->respond($request, __(':count postings restored.', ['count' => $count]), ['count' => $count]);
    }

    public function dismissRequirement(Request $request, string $uuid, string $ruuid, DismissRequirementHandler $handler): RedirectResponse
    {
        (void) $handler->handle($ruuid, $this->ownerId($request));

        return back()->with('success', __('Requirement dismissed — rescore to apply.'));
    }

    /**
     * The list writes through `fetch()` (Pinia Colada), which follows a 302
     * while keeping DELETE/PATCH — a `back()` there lands on a GET-only route
     * and reports a 405 for a write that succeeded. JSON callers get JSON;
     * Inertia callers keep the flash redirect.
     *
     * @param  array<string, mixed>  $payload
     */
    private function respond(Request $request, string $message, array $payload = []): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, ...$payload])
            : back()->with('success', $message);
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
