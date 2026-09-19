<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\DTOs\LeadDetailData;
use Modules\LeadScout\Application\DTOs\LeadFilterData;
use Modules\LeadScout\Application\DTOs\LeadListItemData;
use Modules\LeadScout\Application\Queries\GetLeadHandler;
use Modules\LeadScout\Application\Queries\ListLeadsHandler;

/**
 * Bandeja JSON endpoints (spec US-5, plan §5).
 */
final readonly class LeadController
{
    public function index(LeadFilterData $filters, ListLeadsHandler $list): JsonResponse
    {
        $page = $list->handle($filters, $filters->perPage ?? 15);

        return response()->json([
            'data' => array_map(LeadListItemData::fromLead(...), $page->items),
            'meta' => [
                'current_page' => $page->currentPage,
                'per_page' => $page->perPage,
                'total' => $page->total,
            ],
        ]);
    }

    public function show(string $uuid, GetLeadHandler $get): LeadDetailData
    {
        return $get->handle($uuid);
    }
}
