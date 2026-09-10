<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Invoices\Application\DTOs\InvoiceDetailData;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Invoices\Application\Queries\GetInvoiceHandler;
use Modules\Invoices\Application\Queries\ListInvoicesHandler;

/**
 * Sanctum-authenticated Invoices API (secondary). Primary UI remains Inertia/web.
 * Scramble documents via return types + `auth:sanctum` — no manual annotations.
 * Authorization is route middleware (`permission:*_INVOICES`).
 */
final readonly class InvoiceApiController
{
    /**
     * List invoices.
     *
     * Returns a paginated, filterable invoice list. `per_page` is capped at
     * 100 to bound resource consumption (OWASP API4).
     */
    public function index(Request $request, ListInvoicesHandler $list): JsonResponse
    {
        $filters = InvoiceFilterData::validateAndCreate($request);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show an invoice.
     *
     * Returns a single invoice by UUID, including line items. The shape is the
     * `InvoiceDetailData` allowlist — the raw model is never serialized, so the
     * settlement snapshot leaves as a masked identifier and never as a full
     * IBAN (OWASP API3 / §12, property-level authorization).
     */
    public function show(string $uuid, GetInvoiceHandler $get): JsonResponse
    {
        return response()->json([
            'data' => InvoiceDetailData::fromModel($get->handle($uuid)),
        ]);
    }
}
