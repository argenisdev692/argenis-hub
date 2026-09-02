<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cvs\Application\DTOs\CvData;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Application\Queries\GetCvHandler;
use Modules\Cvs\Application\Queries\ListCvsHandler;
use Shared\Domain\Ports\StoragePort;

/**
 * Sanctum-authenticated Cvs API (secondary). Primary UI remains Inertia/web.
 * Scramble documents via return types + `auth:sanctum` — no manual annotations.
 * Authorization is route middleware (`permission:*_CVS`) plus per-row ownership.
 */
final readonly class CvApiController
{
    private const int DOWNLOAD_URL_TTL_MINUTES = 15;

    /**
     * List CVs.
     *
     * Returns a paginated, filterable list of the authenticated user's own CVs.
     * `per_page` is capped at 100 to bound resource consumption (OWASP API4).
     */
    public function index(Request $request, ListCvsHandler $list): JsonResponse
    {
        $filters = CvFilterData::validateAndCreate($request);
        $cvs = $list->handle(
            $filters,
            (int) $request->user()->id,
            min(max($request->integer('per_page', 15), 1), 100),
        );

        return response()->json(CvData::collect($cvs));
    }

    /**
     * Show a CV.
     *
     * Returns one of the authenticated user's own CVs by UUID, including a
     * short-lived signed download URL. A CV owned by anyone else is a 404.
     */
    public function show(Request $request, string $uuid, GetCvHandler $get, StoragePort $storage): JsonResponse
    {
        $cv = $get->handle($uuid, (int) $request->user()->id);

        return response()->json([
            'data' => CvData::fromModel(
                $cv,
                $storage->temporaryUrl($cv->file_path, now()->addMinutes(self::DOWNLOAD_URL_TTL_MINUTES)),
            ),
        ]);
    }
}
