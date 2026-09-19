<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Commands\AuditCvHandler;
use Modules\CvJobStudio\Application\Commands\ConfirmCvStructureHandler;
use Modules\CvJobStudio\Application\Commands\ExportCvVersionHandler;
use Modules\CvJobStudio\Application\Commands\ParseCvStructureHandler;
use Modules\CvJobStudio\Application\Commands\PromoteVersionToCvsHandler;
use Modules\CvJobStudio\Application\Commands\RewriteCvHandler;
use Modules\CvJobStudio\Application\Commands\SubmitMetricAnswersHandler;
use Modules\CvJobStudio\Application\DTOs\AuditCvData;
use Modules\CvJobStudio\Application\DTOs\ExportCvVersionData;
use Modules\CvJobStudio\Application\DTOs\RewriteCvData;
use Modules\CvJobStudio\Application\DTOs\SelectCvData;
use Modules\CvJobStudio\Application\DTOs\SubmitMetricAnswersData;
use Modules\CvJobStudio\Application\Queries\ListCvVersionsHandler;
use Shared\Domain\Ports\StoragePort;

/**
 * CV lifecycle: parse → confirm → audit → answers → rewrite → export
 * (US-1, US-2). Every step resolves the `Modules\Cvs` row read-only.
 */
final readonly class StudioCvController
{
    public function parse(Request $request, SelectCvData $data, ParseCvStructureHandler $parse): JsonResponse
    {
        $structure = $parse->handle($data->cvUuid, $this->ownerId($request));

        return response()->json(['data' => ['uuid' => $structure->uuid]], 201);
    }

    public function confirm(Request $request, string $uuid, ConfirmCvStructureHandler $confirm): RedirectResponse
    {
        (void) $confirm->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Structure confirmed.'));
    }

    public function audit(Request $request, AuditCvData $data, AuditCvHandler $audit): InertiaResponse|JsonResponse
    {
        $result = $audit->handle($data->cvUuid, $data->targetJobTitle, $this->ownerId($request));

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

    public function rewrite(Request $request, string $uuid, RewriteCvData $data, RewriteCvHandler $handler): JsonResponse
    {
        $version = $handler->handle($uuid, $data->language, $this->ownerId($request));

        return response()->json(['data' => ['uuid' => $version->uuid]], 201);
    }

    /**
     * Generates the file into private R2 storage and hands back a 5-minute
     * signed URL to it (OWASP §15: R2 only through signed URLs). The page
     * follows `download_url`; `download_name` is the ATS-friendly suggested
     * filename (`Firstname-Lastname-Resume.pdf`, never `resume_final_v4`).
     */
    public function export(Request $request, string $uuid, ExportCvVersionData $data, ExportCvVersionHandler $handler, StoragePort $storage): JsonResponse
    {
        $export = $handler->handle($uuid, $data->format, $this->ownerId($request));

        return response()->json(['data' => [
            'uuid' => $export->uuid,
            'verified' => $export->text_extraction_verified,
            'download_url' => $storage->temporaryUrl($export->path, now()->addMinutes(5)),
            'download_name' => self::downloadName($request, $data->format),
        ]], 201);
    }

    /**
     * The "set as primary CV" confirmation: promotes the version to a new
     * canonical `cvs` row (supersede, never overwrite) and re-parses it so
     * every RAG consumer sees it immediately.
     */
    public function promote(Request $request, string $uuid, PromoteVersionToCvsHandler $promote): JsonResponse
    {
        /** @var array{title?: string} $validated */
        $validated = $request->validate(['title' => ['sometimes', 'nullable', 'string', 'max:255']]);

        $cv = $promote->handle($uuid, $this->ownerId($request), $validated['title'] ?? null);

        return response()->json(['data' => ['uuid' => $cv->uuid]], 201);
    }

    public function versions(Request $request, ListCvVersionsHandler $list): InertiaResponse|JsonResponse
    {
        $versions = $list->handle($this->ownerId($request), $request->integer('per_page', 15));

        return match ($request->expectsJson()) {
            true => response()->json($versions),
            false => Inertia::render('cv-studio/Versions/Index', ['versions' => $versions]),
        };
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }

    private static function downloadName(Request $request, string $format): string
    {
        $user = $request->user();
        $slug = Str::slug(trim(sprintf(
            '%s %s',
            (string) ($user->first_name ?? ''),
            (string) ($user->last_name ?? ''),
        )));

        return ($slug !== '' ? $slug : 'cv').'-Resume.'.$format;
    }
}
