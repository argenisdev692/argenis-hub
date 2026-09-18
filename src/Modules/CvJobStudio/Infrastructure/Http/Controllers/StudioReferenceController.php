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
    /** The Inertia branch only picks the tab — the list itself is fetched as JSON by Pinia Colada. */
    public function index(Request $request, ListReferencesHandler $list): InertiaResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return Inertia::render('cv-studio/Postings/Index', ['tab' => 'manual']);
        }

        return response()->json($list->handle($this->ownerId($request)));
    }

    public function paste(Request $request, string $uuid, PasteJobTextData $data, PasteJobTextHandler $handler): RedirectResponse|JsonResponse
    {
        (void) $handler->handle($uuid, $data->text, $this->ownerId($request));

        $message = __('Posting text saved — scored as supplied by you.');

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
