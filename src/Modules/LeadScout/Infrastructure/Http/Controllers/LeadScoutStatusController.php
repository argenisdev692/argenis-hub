<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Ops liveness probe for the LeadScout pipeline (scheduler + workers).
 * Session-authenticated like every other route of the module (OWASP §1).
 */
final readonly class LeadScoutStatusController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'module' => 'lead-scout',
            'rules_version' => config('lead-scout.rules_version'),
            'ok' => true,
        ]);
    }
}
