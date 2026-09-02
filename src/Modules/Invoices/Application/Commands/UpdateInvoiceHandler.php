<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\DTOs\InvoiceData;
use Modules\Invoices\Application\Support\InvoiceCacheKeys;
use Modules\Invoices\Application\Support\InvoicePaymentResolver;
use Modules\Invoices\Application\Support\InvoiceTotalsCalculator;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Queue\GenerateInvoicePdfJob;

final readonly class UpdateInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryPort $invoices,
        private Cache $cache,
    ) {}

    public function handle(InvoiceEloquentModel $invoice, InvoiceData $data): InvoiceEloquentModel
    {
        $client = ClientEloquentModel::query()->where('uuid', $data->clientUuid)->first()
            ?? throw ValidationException::withMessages([
                'client_uuid' => [__('The selected client is invalid.')],
            ]);

        $parsed = InvoiceTotalsCalculator::parseInvoiceNumber($data->invoiceNumber);
        $serviceIds = $this->invoices->mapServiceIdsByUuid(
            InvoiceTotalsCalculator::collectServiceUuids($data->items),
        );
        $productIds = $this->invoices->mapProductIdsByUuid(
            InvoiceTotalsCalculator::collectProductUuids($data->items),
        );
        $productId = $this->invoices->findProductIdByUuid($data->productUuid);
        $totals = InvoiceTotalsCalculator::compute($data, $serviceIds, $productIds);
        $payment = InvoicePaymentResolver::resolve($data);

        if ($this->invoices->numberExists(
            $data->invoiceNumber,
            $parsed['year'],
            $parsed['sequence'],
            $invoice->uuid,
        )) {
            throw ValidationException::withMessages([
                'invoice_number' => [__('This invoice number is already used for that year.')],
            ]);
        }

        $updated = DB::transaction(fn () => $this->invoices->updateWithItems($invoice, [
            'client_id' => $client->id,
            'product_id' => $productId,
            'invoice_number' => $data->invoiceNumber,
            'sequence' => $parsed['sequence'],
            'year' => $parsed['year'],
            'issue_date' => $data->issueDate,
            'due_date' => $data->dueDate,
            'currency' => $data->currency,
            'tax_mode' => $data->taxMode,
            'tax_rate' => $data->taxMode === 'PERCENT' ? ($data->taxRate ?? 0.0) : null,
            'tax_label' => $data->taxLabel,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'is_paid' => $data->isPaid,
            ...$payment,
            'notes' => $data->notes,
            'additional_notes' => $data->additionalNotes,
        ], $totals['items']));

        $this->cache->forget(InvoiceCacheKeys::invoice($updated->uuid));
        $this->cache->forget(InvoiceCacheKeys::pdf($updated->uuid));
        GenerateInvoicePdfJob::dispatch($updated->uuid)->afterCommit();

        return $updated;
    }
}
