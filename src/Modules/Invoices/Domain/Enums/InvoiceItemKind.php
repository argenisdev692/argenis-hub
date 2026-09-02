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
}
