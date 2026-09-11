<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Shared\Domain\Enums\BillingUnit;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One billable line. A line may come from the freelance service catalog
 * (`kind = SERVICE` + `serviceUuid`), from the product catalog
 * (`kind = COURSE|VIDEO` + `productUuid`), or be free text (`kind = CUSTOM`).
 *
 * `unit` is what makes a training line read as `25 horas x 52,00 EUR/hora`
 * instead of `25 x 52,00 EUR`, and it also selects the unit-price column header
 * on the PDF.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class InvoiceItemData extends Data
{
    public function __construct(
        public string $title,
        public float $quantity,
        public float $unitPrice,
        public InvoiceItemKind $kind = InvoiceItemKind::Custom,
        public BillingUnit $unit = BillingUnit::Unit,
        public ?string $serviceUuid = null,
        public ?string $productUuid = null,
        public ?string $description = null,
        public int $sortOrder = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return self::lineRules();
    }

    /**
     * The rules of one line, keyed under `$prefix`: `''` when this DTO validates
     * itself, `items.*.` when {@see InvoiceData} validates its nested lines — one
     * copy, so the two can never drift apart.
     *
     * @return array<string, mixed>
     */
    public static function lineRules(string $prefix = ''): array
    {
        return [
            "{$prefix}title" => ['required', 'string', 'max:255'],
            // TEXT column: a training line carries a bulleted session breakdown.
            "{$prefix}description" => ['nullable', 'string', 'max:5000'],
            "{$prefix}kind" => ['sometimes', 'required', 'string', 'in:'.implode(',', InvoiceItemKind::values())],
            "{$prefix}unit" => ['sometimes', 'required', 'string', 'in:'.implode(',', BillingUnit::values())],
            "{$prefix}quantity" => ['required', 'numeric', 'min:0.01', 'max:999999'],
            "{$prefix}unit_price" => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            "{$prefix}service_uuid" => ['nullable', 'uuid', Rule::exists('services', 'uuid')->withoutTrashed()],
            // A COURSE / VIDEO line without a catalog row behind it is a lie:
            // the PDF would bill training that the product catalog never sold.
            "{$prefix}product_uuid" => [
                'nullable',
                "required_if:{$prefix}kind,".implode(',', InvoiceItemKind::productBackedValues()),
                'uuid',
                Rule::exists('products', 'uuid')->withoutTrashed(),
            ],
            "{$prefix}sort_order" => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
