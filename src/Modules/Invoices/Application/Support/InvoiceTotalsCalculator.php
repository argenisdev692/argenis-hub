<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Modules\Invoices\Application\DTOs\InvoiceData;
use Modules\Invoices\Application\DTOs\InvoiceItemData;
use Modules\Invoices\Domain\Enums\InvoiceItemKind;
use Modules\Invoices\Domain\Enums\TaxMode;

/**
 * Shared totals / item mapping for create and update invoice handlers.
 */
final readonly class InvoiceTotalsCalculator
{
    /**
     * A line that bills a catalog product takes its kind from the product's
     * type, never from the request: a `COURSE` line pointing at a video course
     * would print a contradiction on a legal document.
     *
     * @param  array<string, int>  $serviceIdsByUuid
     * @param  array<string, array{id: int, kind: InvoiceItemKind}>  $productLinesByUuid
     * @return array{subtotal: float, tax_amount: float, total: float, items: list<array<string, mixed>>}
     */
    #[\NoDiscard]
    public static function compute(
        InvoiceData $data,
        array $serviceIdsByUuid = [],
        array $productLinesByUuid = [],
    ): array {
        $items = [];
        $subtotal = 0.0;

        foreach ($data->items as $index => $item) {
            if (! $item instanceof InvoiceItemData) {
                $item = InvoiceItemData::from($item);
            }

            $quantity = round($item->quantity, 2);
            $unitPrice = round($item->unitPrice, 2);
            $amount = round($quantity * $unitPrice, 2);
            $subtotal += $amount;

            $productLine = $item->productUuid !== null ? ($productLinesByUuid[$item->productUuid] ?? null) : null;

            $items[] = [
                'service_id' => $item->serviceUuid !== null ? ($serviceIdsByUuid[$item->serviceUuid] ?? null) : null,
                'product_id' => $productLine['id'] ?? null,
                'kind' => $productLine['kind'] ?? $item->kind,
                'unit' => $item->unit,
                'sort_order' => $item->sortOrder > 0 ? $item->sortOrder : $index,
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ];
        }

        $subtotal = round($subtotal, 2);
        $taxAmount = $data->taxMode === TaxMode::Percent
            ? round($subtotal * (($data->taxRate ?? 0.0) / 100), 2)
            : 0.0;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => round($subtotal + $taxAmount, 2),
            'items' => $items,
        ];
    }

    /**
     * @param  list<InvoiceItemData|array<string, mixed>>  $items
     * @return list<string>
     */
    public static function collectServiceUuids(array $items): array
    {
        return self::collectUuids($items, 'serviceUuid');
    }

    /**
     * @param  list<InvoiceItemData|array<string, mixed>>  $items
     * @return list<string>
     */
    public static function collectProductUuids(array $items): array
    {
        return self::collectUuids($items, 'productUuid');
    }

    /**
     * Parse `007/2026` into sequence + year.
     *
     * @return array{sequence: int, year: int}
     */
    public static function parseInvoiceNumber(string $invoiceNumber): array
    {
        [$sequence, $year] = explode('/', $invoiceNumber, 2);

        return [
            'sequence' => (int) $sequence,
            'year' => (int) $year,
        ];
    }

    #[\NoDiscard]
    public static function formatInvoiceNumber(int $sequence, int $year): string
    {
        return sprintf('%03d/%d', $sequence, $year);
    }

    /**
     * @param  list<InvoiceItemData|array<string, mixed>>  $items
     * @param  'serviceUuid'|'productUuid'  $property
     * @return list<string>
     */
    private static function collectUuids(array $items, string $property): array
    {
        $uuids = [];

        foreach ($items as $item) {
            if (! $item instanceof InvoiceItemData) {
                $item = InvoiceItemData::from($item);
            }

            if ($item->{$property} !== null) {
                $uuids[] = $item->{$property};
            }
        }

        return array_values(array_unique($uuids));
    }
}
