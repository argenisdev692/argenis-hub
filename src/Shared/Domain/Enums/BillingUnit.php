<?php

declare(strict_types=1);

namespace Shared\Domain\Enums;

/**
 * Unit of measure a billable line is priced in.
 *
 * Shared because both sides of the relationship need it and neither owns the
 * other: `products.default_unit` proposes it, `invoice_items.unit` records it.
 * It drives the rendered quantity ("25 horas") AND the unit-price column header
 * ("Precio/Hora" vs "Precio unitario") on the PDF.
 */
enum BillingUnit: string
{
    case Unit = 'UNIT';
    case Hour = 'HOUR';
    case Session = 'SESSION';
    case Day = 'DAY';
    case Month = 'MONTH';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Fractional quantities are meaningful for time-based units only
     * (`3,5 horas`); a unit count renders without decimals.
     */
    public function allowsFractionalQuantity(): bool
    {
        return match ($this) {
            self::Hour, self::Day, self::Month => true,
            self::Unit, self::Session => false,
        };
    }
}
