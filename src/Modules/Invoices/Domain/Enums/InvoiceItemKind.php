<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Enums;

/**
 * What a line item is billing. `Service` links to the freelance service
 * catalog, `Course` / `Video` link to the product catalog, `Custom` is
 * free text with no catalog row behind it.
 */
enum InvoiceItemKind: string
{
    case Service = 'SERVICE';
    case Course = 'COURSE';
    case Video = 'VIDEO';
    case Custom = 'CUSTOM';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresProduct(): bool
    {
        return $this === self::Course || $this === self::Video;
    }

    /**
     * The kinds that are meaningless without a catalog row behind them — the
     * single source for the `required_if` guard on `items.*.product_uuid`, so
     * the rule cannot drift from {@see self::requiresProduct()}.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public static function productBackedValues(): array
    {
        return array_values(array_map(
            static fn (self $kind): string => $kind->value,
            array_filter(self::cases(), static fn (self $kind): bool => $kind->requiresProduct()),
        ));
    }
}
