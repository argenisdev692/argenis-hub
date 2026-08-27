<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\ContactSupport\Application\Commands\SubmitContactSupportHandler;
use Modules\ContactSupport\Application\DTOs\PublicContactSupportData;
use Modules\ContactSupport\Infrastructure\Http\Requests\StorePublicContactSupportRequest;
use Modules\Services\Infrastructure\Http\Controllers\Api\PublicServiceController;
use Spatie\Honeypot\ProtectAgainstSpam;

/**
 * The one public, unauthenticated write in this module: the landing-page
 * contact form. Same defence posture as the other public endpoints
 * ({@see PublicServiceController}):
 *
 * - a Spatie Data allowlist on the way out ({@see PublicContactSupportData}) so
 *   internal columns and triage state can never appear;
 * - a per-IP rate limiter (`throttle:public-contact-supports`) so an open
 *   endpoint cannot become a cheap flood vector (OWASP §14 / §15.5);
 * - honeypot spam protection ({@see ProtectAgainstSpam}) on the
 *   route (OWASP §15.5);
 * - `StorePublicContactSupportRequest` as the authoritative validation gate.
 */
final readonly class PublicContactSupportController
{
    public function __construct(private SubmitContactSupportHandler $submitContactSupport) {}

    /**
     * Record a contact-support request submitted from the public site.
     */
    public function __invoke(StorePublicContactSupportRequest $request): JsonResponse
    {
        $acknowledgement = $this->submitContactSupport->handle(
            $request->validated(),
            $request->user()?->getAuthIdentifier() !== null
                ? (int) $request->user()->getAuthIdentifier()
                : null,
        );

        return response()->json($acknowledgement, 201);
    }
}
