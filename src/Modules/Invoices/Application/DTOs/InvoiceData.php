<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Shared\Domain\Enums\BillingUnit;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Fused create/update DTO for an invoice with nested line items.
 * Items use a plain array (not DataCollection) so handlers can iterate
 * without Spatie's transform pipeline TypeErroring on raw request arrays.
 *
 * When `is_paid` is true, payment method / date / amount received are required
 * and rendered on the PDF as the PAYMENT RECEIVED block. `paymentAccountUuid`
 * is optional but recommended — it is what makes the PDF print the real IBAN or
 * Remitly details instead of a bare method name.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class InvoiceData extends Data
{
    /**
     * @param  list<InvoiceItemData>  $items
     */
    public function __construct(
        public string $clientUuid,
        public string $invoiceNumber,
        public string $issueDate,
        public string $dueDate,
        #[DataCollectionOf(InvoiceItemData::class)]
        public array $items,
        public ?string $productUuid = null,
        public string $currency = 'USD',
        public string $taxMode = 'EXEMPT',
        public ?float $taxRate = 0.0,
        public string $taxLabel = 'IVA',
        public bool $isPaid = false,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $paymentAccountUuid = null,
        public ?string $transferNumber = null,
        public ?string $paymentDate = null,
        public ?float $amountReceived = null,
        public ?string $notes = null,
        public ?string $additionalNotes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'client_uuid' => ['required', 'uuid', 'exists:clients,uuid'],
            'product_uuid' => ['nullable', 'uuid', 'exists:products,uuid'],
            'invoice_number' => ['required', 'string', 'max:32', 'regex:/^\d{1,6}\/\d{4}$/'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3', 'alpha', 'uppercase'],
            'tax_mode' => ['required', 'string', 'in:EXEMPT,PERCENT'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_label' => ['required', 'string', 'max:32'],
            'is_paid' => ['required', 'boolean'],
            'payment_method' => [
                'nullable',
                'required_if:is_paid,true',
                'string',
                'in:'.implode(',', PaymentMethod::values()),
            ],
            'payment_account_uuid' => ['nullable', 'uuid', 'exists:payment_accounts,uuid'],
            'transfer_number' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'required_if:is_paid,true', 'date'],
            'amount_received' => ['nullable', 'required_if:is_paid,true', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.kind' => ['sometimes', 'required', 'string', 'in:'.implode(',', InvoiceItemKind::values())],
            'items.*.unit' => ['sometimes', 'required', 'string', 'in:'.implode(',', BillingUnit::values())],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'items.*.service_uuid' => ['nullable', 'uuid', 'exists:services,uuid'],
            'items.*.product_uuid' => ['nullable', 'uuid', 'exists:products,uuid'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
