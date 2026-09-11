<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One invoice with its lines — what the detail dialog renders and what the edit
 * form hydrates from.
 *
 * `paymentDetails` is the snapshot taken when the invoice was issued, exposed
 * as a label-only summary: the UI needs to show WHICH rail settled it, never
 * the full IBAN, so the identifier is masked here the same way the export
 * masks it.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class InvoiceDetailData extends Data
{
    /**
     * @param  list<InvoiceItemDetailData>  $items
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $invoiceNumber,
        public readonly int $sequence,
        public readonly int $year,
        public readonly string $issueDate,
        public readonly string $dueDate,
        public readonly string $currency,
        public readonly TaxMode $taxMode,
        public readonly ?float $taxRate,
        public readonly string $taxLabel,
        public readonly float $subtotal,
        public readonly float $taxAmount,
        public readonly float $total,
        public readonly bool $isPaid,
        public readonly ?PaymentMethod $paymentMethod,
        public readonly ?string $paymentAccountUuid,
        public readonly ?string $paymentAccountLabel,
        public readonly ?string $paymentAccountMasked,
        public readonly ?string $transferNumber,
        public readonly ?string $paymentDate,
        public readonly ?float $amountReceived,
        public readonly ?string $notes,
        public readonly ?string $additionalNotes,
        public readonly ?string $clientUuid,
        public readonly ?string $clientName,
        public readonly ?string $productUuid,
        public readonly ?string $productTitle,
        #[DataCollectionOf(InvoiceItemDetailData::class)]
        public readonly array $items,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(InvoiceEloquentModel $invoice): self
    {
        $snapshot = is_array($invoice->payment_details_json) ? $invoice->payment_details_json : [];

        return new self(
            uuid: $invoice->uuid,
            invoiceNumber: $invoice->invoice_number,
            sequence: $invoice->sequence,
            year: $invoice->year,
            issueDate: $invoice->issue_date->toDateString(),
            dueDate: $invoice->due_date->toDateString(),
            currency: $invoice->currency,
            taxMode: $invoice->tax_mode,
            taxRate: $invoice->tax_rate !== null ? (float) $invoice->tax_rate : null,
            taxLabel: $invoice->tax_label,
            subtotal: (float) $invoice->subtotal,
            taxAmount: (float) $invoice->tax_amount,
            total: (float) $invoice->total,
            isPaid: $invoice->is_paid,
            paymentMethod: $invoice->payment_method,
            paymentAccountUuid: $invoice->relationLoaded('paymentAccount')
                ? $invoice->paymentAccount?->uuid
                : null,
            paymentAccountLabel: isset($snapshot['label']) ? (string) $snapshot['label'] : null,
            paymentAccountMasked: self::mask(
                $snapshot['iban'] ?? $snapshot['account_number'] ?? null,
            ),
            transferNumber: $invoice->transfer_number,
            paymentDate: $invoice->payment_date?->toDateString(),
            amountReceived: $invoice->amount_received !== null ? (float) $invoice->amount_received : null,
            notes: $invoice->notes,
            additionalNotes: $invoice->additional_notes,
            clientUuid: $invoice->relationLoaded('client') ? $invoice->client?->uuid : null,
            clientName: $invoice->relationLoaded('client') ? $invoice->client?->client_name : null,
            productUuid: $invoice->relationLoaded('product') ? $invoice->product?->uuid : null,
            productTitle: $invoice->relationLoaded('product') ? $invoice->product?->title : null,
            items: $invoice->relationLoaded('items')
                ? $invoice->items
                    ->map(static fn (InvoiceItemEloquentModel $i): InvoiceItemDetailData => InvoiceItemDetailData::fromModel($i))
                    ->values()
                    ->all()
                : [],
            createdAt: $invoice->created_at?->toIso8601String(),
            updatedAt: $invoice->updated_at?->toIso8601String(),
            deletedAt: $invoice->deleted_at?->toIso8601String(),
        );
    }

    /**
     * Last four characters only — enough for the operator to recognise the rail,
     * useless to anyone who gets hold of the response.
     */
    private static function mask(mixed $identifier): ?string
    {
        $value = trim((string) ($identifier ?? ''));

        if ($value === '') {
            return null;
        }

        return mb_strlen($value) <= 4
            ? str_repeat('•', mb_strlen($value))
            : str_repeat('•', 4).' '.mb_substr($value, -4);
    }
}
