<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Shared\Domain\Enums\Currency;
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
        public TaxMode $taxMode = TaxMode::Exempt,
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
            'product_uuid' => ['nullable', 'uuid', Rule::exists('products', 'uuid')->withoutTrashed()],
            'invoice_number' => ['required', 'string', 'max:32', 'regex:/^\d{1,6}\/\d{4}$/'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            // Only the currencies the invoice PDF can render.
            'currency' => ['required', 'string', 'in:'.implode(',', Currency::values())],
            'tax_mode' => ['required', 'string', 'in:'.implode(',', TaxMode::values())],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_label' => ['required', 'string', 'max:32'],
            'is_paid' => ['required', 'boolean'],
            'payment_method' => [
                'nullable',
                'required_if:is_paid,true',
                'string',
                'in:'.implode(',', PaymentMethod::values()),
            ],
            // A suspended or inactive rail never lands on a new document.
            'payment_account_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('payment_accounts', 'uuid')->withoutTrashed()->where('is_active', true),
            ],
            'transfer_number' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'required_if:is_paid,true', 'date'],
            'amount_received' => ['nullable', 'required_if:is_paid,true', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            ...InvoiceItemData::lineRules('items.*.'),
        ];
    }
}
