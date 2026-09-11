<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Enums;

/**
 * How VAT applies to an invoice. `Exempt` is the cross-border B2B reverse
 * charge every issued invoice uses; `Percent` applies `tax_rate` to the
 * subtotal.
 */
enum TaxMode: string
{
    case Exempt = 'EXEMPT';
    case Percent = 'PERCENT';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
