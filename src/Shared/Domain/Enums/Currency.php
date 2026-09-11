<?php

declare(strict_types=1);

namespace Shared\Domain\Enums;

/**
 * Currencies an invoice, a catalog product and a payment account may carry.
 *
 * Shared because the three modules must agree on ONE list: a product priced in
 * a currency the invoice PDF cannot render, or an account pinned to a currency
 * no invoice can use, is a record that can never be billed correctly. EUR bills
 * the Spanish training clients, USD the US web clients and GBP the UK clients;
 * supporting another one is a new case plus its symbol, nothing else.
 */
enum Currency: string
{
    case Eur = 'EUR';
    case Usd = 'USD';
    case Gbp = 'GBP';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Eur => '€',
            self::Usd => '$',
            self::Gbp => '£',
        };
    }
}
