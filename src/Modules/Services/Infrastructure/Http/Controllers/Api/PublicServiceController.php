<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Services\Application\DTOs\PublicServiceData;
use Modules\Services\Application\Queries\ListPublicServicesHandler;

/**
 * Public service catalog for the landing page `<select>` and the standalone
 * Astro sites — same reasoning as {@see
 * \Modules\Company\Infrastructure\Http\Controllers\Api\PublicCompanyController}:
 *
 * - a Spatie Data allowlist ({@see PublicServiceData})
 *   so the owner id and lifecycle timestamps physically cannot appear;
 * - a rate limiter, so an unauthenticated endpoint cannot become a cheap
 *   amplifier (OWASP §14);
 * - a 30-minute cache in the query handler, so catalog browsing does not
 *   become database traffic for a table that changes a few times a year.
 */
final readonly class PublicServiceController
{
    public function __construct(private ListPublicServicesHandler $listPublicServices) {}

    /**
     * List the active services, in display order.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json($this->listPublicServices->handle());
    }
}
