<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Support;

use Shared\Domain\Enums\BillingUnit;

/**
 * Locale-correct money and quantity rendering for the invoice PDF.
 *
 * The Imagina/EU invoices read `1.300,00 €` — symbol suffixed, dot thousands,
 * comma decimals — while the US invoices read `$1,300.00`. Formatting every
 * document the US way (the previous behaviour) makes a Spanish B2B invoice look
 * wrong to the client receiving it.
 */
final readonly class InvoiceMoneyFormatter
{
    /**
     * @param  'en'|'es'|'pt'  $locale
     */
    public function __construct(
        private string $locale,
        private string $currency,
        private string $symbol,
    ) {}

    #[\NoDiscard]
    public function money(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);

        if ($this->locale === 'en') {
            return $this->symbol.number_format($value, 2, '.', ',').' '.$this->currency;
        }

        return number_format($value, 2, ',', '.').' '.$this->symbol;
    }

    /**
     * `25 horas`, `3,5 horas`, `1 sesión`, `2` — never the previous
     * `number_format($q, 0)`, which silently rounded 3,5 hours up to 4.
     */
    #[\NoDiscard]
    public function quantity(float|int|string|null $quantity, BillingUnit $unit, string $unitLabel): string
    {
        $value = (float) ($quantity ?? 0);
        $decimals = $this->decimalsFor($value, $unit);

        $number = $this->locale === 'en'
            ? number_format($value, $decimals, '.', ',')
            : number_format($value, $decimals, ',', '.');

        return $unitLabel === '' ? $number : $number.' '.$unitLabel;
    }

    /**
     * Time-based units keep a decimal when there is one to keep; a unit count
     * never grows a spurious `,00`.
     */
    private function decimalsFor(float $value, BillingUnit $unit): int
    {
        if (! $unit->allowsFractionalQuantity()) {
            return 0;
        }

        return abs($value - round($value)) < 0.005 ? 0 : 2;
    }
}
