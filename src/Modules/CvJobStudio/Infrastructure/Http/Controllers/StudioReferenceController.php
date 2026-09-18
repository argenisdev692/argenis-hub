<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Commands\PasteJobTextHandler;
use Modules\CvJobStudio\Application\DTOs\PasteJobTextData;
use Modules\CvJobStudio\Application\Queries\ListReferencesHandler;

/** References + paste-JD dialog (T-158, T-138): open manually, then paste. */
final readonly class StudioReferenceController
{
    public function index(Request $request, ListReferencesHandler $list): InertiaResponse|JsonResponse
    {
        $references = $list->handle($this->ownerId($request));

        return match ($request->expectsJson()) {
            true => response()->json($references),
            false => Inertia::render('cv-studio/Postings/Index', ['references' => $references, 'tab' => 'manual']),
        };
    }

    public function paste(Request $request, string $uuid, PasteJobTextData $data, PasteJobTextHandler $handler): RedirectResponse
    {
        $posting = $handler->handle($uuid, $data->text, $this->ownerId($request));

        return back()->with('success', __('Posting text saved — scored as supplied by you.'));
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
