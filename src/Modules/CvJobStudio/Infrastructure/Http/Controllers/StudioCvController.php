<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Commands\AuditCvHandler;
use Modules\CvJobStudio\Application\Commands\ConfirmCvStructureHandler;
use Modules\CvJobStudio\Application\Commands\ExportCvVersionHandler;
use Modules\CvJobStudio\Application\Commands\ParseCvStructureHandler;
use Modules\CvJobStudio\Application\Commands\RewriteCvHandler;
use Modules\CvJobStudio\Application\Commands\SubmitMetricAnswersHandler;
use Modules\CvJobStudio\Application\DTOs\SubmitMetricAnswersData;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;

/**
 * CV lifecycle: parse → confirm → audit → answers → rewrite → export
 * (US-1, US-2). Every step resolves the `Modules\Cvs` row read-only.
 */
final readonly class StudioCvController
{
    public function parse(Request $request, ParseCvStructureHandler $parse): JsonResponse
    {
        /** @var array{cv_uuid?: string} $validated */
        $validated = $request->validate(['cv_uuid' => ['nullable', 'string', 'uuid']]);

        $structure = $parse->handle($validated['cv_uuid'] ?? null, $this->ownerId($request));

        return response()->json(['data' => ['uuid' => $structure->uuid]], 201);
    }

    public function confirm(Request $request, string $uuid, ConfirmCvStructureHandler $confirm): RedirectResponse
    {
        (void) $confirm->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Structure confirmed.'));
    }

    public function audit(Request $request, AuditCvHandler $audit): InertiaResponse|JsonResponse
    {
        /** @var array{cv_uuid?: string, target_job_title?: string} $validated */
        $validated = $request->validate([
            'cv_uuid' => ['nullable', 'string', 'uuid'],
            'target_job_title' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $audit->handle($validated['cv_uuid'] ?? null, $validated['target_job_title'] ?? null, $this->ownerId($request));

        return match ($request->expectsJson()) {
            true => response()->json(['data' => $result], 201),
            false => Inertia::render('cv-studio/Cvs/Audit', ['audit' => $result]),
        };
    }

    public function answers(Request $request, string $uuid, SubmitMetricAnswersData $data, SubmitMetricAnswersHandler $handler): RedirectResponse
    {
        $count = $handler->handle($uuid, $data->answers, $this->ownerId($request));

        return back()->with('success', __(':count answer(s) saved.', ['count' => $count]));
    }

    public function rewrite(Request $request, string $uuid, RewriteCvHandler $handler): JsonResponse
    {
        /** @var array{language?: string} $validated */
        $validated = $request->validate(['language' => ['nullable', 'string', 'in:es,en,pt-PT']]);

        $version = $handler->handle($uuid, $validated['language'] ?? 'en', $this->ownerId($request));

        return response()->json(['data' => ['uuid' => $version->uuid]], 201);
    }

    public function export(Request $request, string $uuid, ExportCvVersionHandler $handler): JsonResponse
    {
        /** @var array{format?: string} $validated */
        $validated = $request->validate(['format' => ['nullable', 'string', 'in:docx,pdf']]);

        $export = $handler->handle($uuid, $validated['format'] ?? 'pdf', $this->ownerId($request));

        return response()->json(['data' => ['uuid' => $export->uuid, 'verified' => $export->text_extraction_verified]], 201);
    }

    public function versions(Request $request): InertiaResponse|JsonResponse
    {
        $versions = StudioCvVersionEloquentModel::query()
            ->ownedBy($this->ownerId($request))
            ->select(['uuid', 'user_id', 'purpose', 'language', 'posting_id', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return match ($request->expectsJson()) {
            true => response()->json($versions),
            false => Inertia::render('cv-studio/Versions/Index', ['versions' => $versions]),
        };
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
