<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;

/**
 * Ops liveness probe for the LeadScout pipeline (scheduler + workers).
 * Session-authenticated like every other route of the module (OWASP §1).
 * Queue depth is exposed so the "Run jobs" dialog can show what is still
 * waiting instead of answering 202 into the void.
 */
final readonly class LeadScoutStatusController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'module' => 'lead-scout',
            'rules_version' => config('lead-scout.rules_version'),
            'queues' => [
                'lead-scout' => Queue::size('lead-scout'),
                'lead-scout-llm' => Queue::size('lead-scout-llm'),
                'default' => Queue::size('default'),
            ],
            'ok' => true,
        ]);
    }
}
