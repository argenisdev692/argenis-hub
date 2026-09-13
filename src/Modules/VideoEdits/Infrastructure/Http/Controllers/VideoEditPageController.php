<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;

/**
 * The two Inertia shells of the module. Neither ships row data: both pages
 * read the `/data/admin/video-edits` JSON surface through Pinia Colada, which
 * is what lets a running edit poll its progress without a full Inertia visit.
 */
final readonly class VideoEditPageController
{
    public function __construct(private VideoEditRepositoryPort $edits) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('video-edits/Index');
    }

    /**
     * Ownership is checked here as well as in the JSON handler, so another
     * user's uuid never renders an empty shell that only 404s after loading.
     */
    public function show(Request $request, string $uuid): InertiaResponse
    {
        abort_if($this->edits->findOwnedByUuid($uuid, (int) $request->user()?->id) === null, 404);

        return Inertia::render('video-edits/Show', ['uuid' => $uuid]);
    }
}
