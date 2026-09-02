<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Shared\Domain\Enums\BillingUnit;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One persisted invoice line, as the detail view and the edit form read it.
 *
 * The write-side counterpart is {@see InvoiceItemData}; this one carries the
 * computed `amount` and the resolved catalog UUIDs, which the write DTO does not.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class InvoiceItemDetailData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly InvoiceItemKind $kind,
        public readonly BillingUnit $unit,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $amount,
        public readonly int $sortOrder,
        public readonly ?string $serviceUuid,
        public readonly ?string $productUuid,
    ) {}

    public static function fromModel(InvoiceItemEloquentModel $item): self
    {
        return new self(
            title: $item->title,
            description: $item->description,
            kind: $item->kind,
            unit: $item->unit,
            quantity: (float) $item->quantity,
            unitPrice: (float) $item->unit_price,
            amount: (float) $item->amount,
            sortOrder: $item->sort_order,
            serviceUuid: $item->relationLoaded('service') ? $item->service?->uuid : null,
            productUuid: $item->relationLoaded('product') ? $item->product?->uuid : null,
        );
    }
}
