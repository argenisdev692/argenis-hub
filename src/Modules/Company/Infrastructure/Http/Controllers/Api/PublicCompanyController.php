<?php

declare(strict_types=1);

namespace Modules\Company\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Company\Application\Queries\GetPublicCompanyHandler;

/**
 * Public company profile for the standalone Astro landing sites.
 *
 * Unauthenticated on purpose, and the only route in the application that is.
 * Everything it returns is already printed on the company own pages — the
 * trading name, the brand marks, the social profiles, the street address — so a
 * token would add friction without adding secrecy, and an Astro static build has
 * nowhere safe to keep one anyway. What does the work instead:
 *
 * - a Spatie Data allowlist, so the fiscal identity, the bank block and the
 *   owner id physically cannot appear in the payload (OWASP §12);
 * - a rate limiter, so an unauthenticated endpoint cannot be turned into a
 *   cheap amplifier (OWASP §14);
 * - a 30-minute cache in the query handler, so landing traffic does not become
 *   database traffic for a row that changes a few times a year.
 *
 * A browser fetching this cross-origin still needs the origin listed in
 * config/cors.php; a build-time or SSR fetch from Astro does not.
 */
final readonly class PublicCompanyController
{
    public function __construct(private GetPublicCompanyHandler $getPublicCompany) {}

    /**
     * Get the public company profile.
     *
     * Returns the trading name, brand-mark URLs, every social profile and the
     * street address. Responds 404 until the installation has been seeded.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json($this->getPublicCompany->handle());
    }
}
