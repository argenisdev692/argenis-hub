<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Invoices\Application\DTOs\InvoiceDetailData;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Invoices\Application\Queries\GetInvoiceHandler;
use Modules\Invoices\Application\Queries\ListInvoicesHandler;

/**
 * Sanctum-authenticated Invoices API (secondary). Primary UI remains Inertia/web.
 * Responses are documented from return types; authorization is route middleware
 * (`permission:*_INVOICES`).
 *
 * Filters are documented from the injected {@see InvoiceFilterData}: Scramble
 * reads a `Data` parameter's rules directly, while the equivalent
 * `InvoiceFilterData::validateAndCreate($request)` call hides them — the rules
 * live in a static method the analyser cannot follow, so this endpoint used to
 * document `per_page` and nothing else. Injection validates identically and is
 * what the web controllers already do.
 *
 * Inference is also better than hand-written attributes here, which is why the
 * ones below are trimmed to descriptions: it derives `maxLength` from
 * `max:255`, `format: uuid` from the `uuid` rule, and the `status` /
 * `payment_status` enums from their `in:` lists — none of which can drift,
 * because there is only one copy.
 */
final readonly class InvoiceApiController
{
    /**
     * List invoices.
     *
     * Returns a paginated, filterable invoice list ordered by issue date then
     * sequence, newest first. `per_page` is capped at 100 to bound resource
     * consumption (OWASP API4).
     *
     * The filters compose: `status` and `payment_status` are orthogonal axes,
     * so `?status=suspended&payment_status=paid` is a meaningful request.
     *
     * `search` matches the invoice number or the client name.
     */
    /*
     * `per_page` is the only parameter annotated here, because it is the only
     * one that is not an `InvoiceFilterData` rule — the handler reads and
     * clamps it straight off the request. Every filter's description lives as a
     * comment above its rule in the DTO, which is where Scramble reads it from
     * (a description-only attribute is ignored; it only takes effect when the
     * attribute also supplies a `type`, which would then replace the inferred
     * schema and reintroduce exactly the drift injection removed).
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        InvoiceFilterData $filters,
        ListInvoicesHandler $list,
    ): JsonResponse {
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
