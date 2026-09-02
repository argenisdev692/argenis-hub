<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Invoices\Application\Commands\BulkDeleteInvoicesHandler;
use Modules\Invoices\Application\Commands\BulkRestoreInvoicesHandler;
use Modules\Invoices\Application\Commands\CreateInvoiceHandler;
use Modules\Invoices\Application\Commands\DeleteInvoiceHandler;
use Modules\Invoices\Application\Commands\RestoreInvoiceHandler;
use Modules\Invoices\Application\Commands\UpdateInvoiceHandler;
use Modules\Invoices\Application\DTOs\InvoiceData;
use Modules\Invoices\Application\DTOs\InvoiceDetailData;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Invoices\Application\Queries\CheckInvoiceNumberHandler;
use Modules\Invoices\Application\Queries\GetInvoiceFormOptionsHandler;
use Modules\Invoices\Application\Queries\GetInvoiceHandler;
use Modules\Invoices\Application\Queries\ListInvoicesHandler;
use Modules\Invoices\Application\Queries\SuggestNextInvoiceNumberHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * The billing admin surface: one Inertia page (`page()`) plus the JSON data
 * endpoints it consumes under `/data/admin/invoices` (Controller Fusion Rule —
 * one entity, one permission set). Each method is guarded by its own permission
 * at the route level (`web.php`), not re-checked here.
 *
 * Export and single-invoice PDF stay in their own invokable controllers:
 * they stream binaries rather than JSON, and the PDF one is Redis-cached.
 */
final readonly class AdminInvoiceController
{
    public function __construct(
        private ListInvoicesHandler $listInvoices,
        private GetInvoiceHandler $getInvoice,
        private CreateInvoiceHandler $createInvoice,
        private UpdateInvoiceHandler $updateInvoice,
        private DeleteInvoiceHandler $deleteInvoice,
        private RestoreInvoiceHandler $restoreInvoice,
        private BulkDeleteInvoicesHandler $bulkDeleteInvoices,
        private BulkRestoreInvoicesHandler $bulkRestoreInvoices,
    ) {}

    /**
     * The table fetches its own rows via Pinia Colada against `index()`, so the
     * page itself carries no server-rendered props.
     */
    public function page(): InertiaResponse
    {
        return Inertia::render('invoices/Index');
    }

    /**
     * `InvoiceFilterData` extends the shared `SoftDeleteFilterData`, which
     * carries no pagination of its own — the page size is read from the request
     * and clamped here rather than widening the shared base for one module.
     */
    public function index(Request $request, InvoiceFilterData $filters): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return response()->json($this->listInvoices->handle($filters, $perPage));
    }

    /**
     * Catalogs the create/edit form needs: clients, services, published
     * products, settlement rails and the enum vocabularies. One request instead
     * of five, because the form cannot render usefully without all of them.
     */
    public function formOptions(GetInvoiceFormOptionsHandler $formOptions): JsonResponse
    {
        return response()->json($formOptions->handle());
    }

    public function nextNumber(Request $request, SuggestNextInvoiceNumberHandler $suggest): JsonResponse
    {
        $year = $request->integer('year');

        return response()->json($suggest->handle($year > 0 ? $year : null));
    }

    public function checkNumber(Request $request, CheckInvoiceNumberHandler $check): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:32', 'regex:/^(\d{1,6}|\d{1,6}\/\d{4})$/'],
            'ignore' => ['nullable', 'uuid'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        return response()->json($check->handle(
            $validated['invoice_number'],
            $validated['ignore'] ?? null,
            isset($validated['year']) ? (int) $validated['year'] : null,
        ));
    }

    public function store(Request $request, InvoiceData $data): JsonResponse
    {
        $invoice = $this->createInvoice->handle($data, (int) $request->user()?->getAuthIdentifier());

        return response()->json(InvoiceDetailData::fromModel($this->getInvoice->handle($invoice->uuid)), 201);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(InvoiceDetailData::fromModel($this->getInvoice->handle($uuid)));
    }

    public function update(string $uuid, InvoiceData $data): JsonResponse
    {
        $this->updateInvoice->handle($this->getInvoice->handle($uuid), $data);

        return response()->json(InvoiceDetailData::fromModel($this->getInvoice->handle($uuid)));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->deleteInvoice->handle($uuid);

        return response()->json(status: 204);
    }

    public function restore(string $uuid): JsonResponse
    {
        $this->restoreInvoice->handle($uuid);

        return response()->json(InvoiceDetailData::fromModel($this->getInvoice->handle($uuid)));
    }

    public function bulkDelete(BulkUuidsData $data): JsonResponse
    {
        return response()->json(['deleted' => $this->bulkDeleteInvoices->handle($data)]);
    }

    public function bulkRestore(BulkUuidsData $data): JsonResponse
    {
        return response()->json(['restored' => $this->bulkRestoreInvoices->handle($data)]);
    }
}
