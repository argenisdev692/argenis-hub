<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One invoice as the admin table renders it.
 *
 * The allowlist behind every `/data/admin/invoices` list response: the
 * auto-increment `id`, `user_id` and the payment snapshot never cross this
 * boundary (OWASP §12) — a settlement IBAN has no business in a list payload.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class InvoiceListItemData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $invoiceNumber,
        public readonly int $sequence,
        public readonly int $year,
        public readonly string $issueDate,
        public readonly string $dueDate,
        public readonly string $currency,
        public readonly float $subtotal,
        public readonly float $taxAmount,
        public readonly float $total,
        public readonly bool $isPaid,
        public readonly ?PaymentMethod $paymentMethod,
        public readonly ?string $clientUuid,
        public readonly ?string $clientName,
        public readonly ?string $productTitle,
        public readonly ?string $createdAt,
        public readonly ?string $deletedAt,
    ) {}

    public static function fromModel(InvoiceEloquentModel $invoice): self
    {
        return new self(
            uuid: $invoice->uuid,
            invoiceNumber: $invoice->invoice_number,
            sequence: $invoice->sequence,
            year: $invoice->year,
            issueDate: $invoice->issue_date->toDateString(),
            dueDate: $invoice->due_date->toDateString(),
            currency: $invoice->currency,
            subtotal: (float) $invoice->subtotal,
            taxAmount: (float) $invoice->tax_amount,
            total: (float) $invoice->total,
            isPaid: $invoice->is_paid,
            paymentMethod: $invoice->payment_method,
            clientUuid: $invoice->relationLoaded('client') ? $invoice->client?->uuid : null,
            clientName: $invoice->relationLoaded('client') ? $invoice->client?->client_name : null,
            productTitle: $invoice->relationLoaded('product') ? $invoice->product?->title : null,
            createdAt: $invoice->created_at?->toIso8601String(),
            deletedAt: $invoice->deleted_at?->toIso8601String(),
        );
    }
}
