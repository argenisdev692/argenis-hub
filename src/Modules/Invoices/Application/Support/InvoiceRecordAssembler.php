<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Illuminate\Validation\ValidationException;
use Modules\Invoices\Application\DTOs\InvoiceData;
use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;

/**
 * Turns a submitted {@see InvoiceData} into the header columns and line rows
 * the repository persists — the single place create AND update resolve the
 * client, enforce the per-year number, link catalog rows, total the lines and
 * snapshot the payment account, so the two handlers cannot drift apart.
 */
final readonly class InvoiceRecordAssembler
{
    public function __construct(private InvoiceRepositoryPort $invoices) {}

    /**
     * `$exceptUuid` is the invoice being edited, so it never collides with its
     * own number.
     *
     * @return array{attributes: array<string, mixed>, items: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    #[\NoDiscard]
    public function assemble(InvoiceData $data, ?string $exceptUuid = null): array
    {
        $clientId = $this->invoices->findClientIdByUuid($data->clientUuid)
            ?? throw ValidationException::withMessages([
                'client_uuid' => [__('The selected client is invalid.')],
            ]);

        $number = InvoiceTotalsCalculator::parseInvoiceNumber($data->invoiceNumber);

        if ($this->invoices->numberExists($data->invoiceNumber, $number['year'], $number['sequence'], $exceptUuid)) {
            throw ValidationException::withMessages([
                'invoice_number' => [__('This invoice number is already used for that year.')],
            ]);
        }

        $totals = InvoiceTotalsCalculator::compute(
            $data,
            $this->invoices->mapServiceIdsByUuid(InvoiceTotalsCalculator::collectServiceUuids($data->items)),
            $this->invoices->mapProductLinesByUuid(InvoiceTotalsCalculator::collectProductUuids($data->items)),
        );

        return [
            'attributes' => [
                'client_id' => $clientId,
                'product_id' => $this->invoices->findProductIdByUuid($data->productUuid),
                'invoice_number' => $data->invoiceNumber,
                'sequence' => $number['sequence'],
                'year' => $number['year'],
                'issue_date' => $data->issueDate,
                'due_date' => $data->dueDate,
                'currency' => $data->currency,
                'tax_mode' => $data->taxMode,
                'tax_rate' => $data->taxMode === TaxMode::Percent ? ($data->taxRate ?? 0.0) : null,
                'tax_label' => $data->taxLabel,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'is_paid' => $data->isPaid,
                ...InvoicePaymentResolver::resolve($data),
                'notes' => $data->notes,
                'additional_notes' => $data->additionalNotes,
            ],
            'items' => $totals['items'],
        ];
    }
}
