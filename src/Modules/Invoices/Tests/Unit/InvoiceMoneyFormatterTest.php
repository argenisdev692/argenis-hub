<?php

declare(strict_types=1);

use Modules\Invoices\Application\Support\InvoiceMoneyFormatter;
use Shared\Domain\Enums\BillingUnit;

it('formats euro amounts the way a spanish client expects', function (): void {
    $formatter = new InvoiceMoneyFormatter('es', 'EUR', '€');

    expect($formatter->money(1300))->toBe('1.300,00 €')
        ->and($formatter->money(312.5))->toBe('312,50 €');
});

it('formats dollar amounts the way a us client expects', function (): void {
    $formatter = new InvoiceMoneyFormatter('en', 'USD', '$');

    expect($formatter->money(1300))->toBe('$1,300.00 USD')
        ->and($formatter->money(35))->toBe('$35.00 USD');
});

it('renders whole hours without spurious decimals', function (): void {
    $formatter = new InvoiceMoneyFormatter('es', 'EUR', '€');

    expect($formatter->quantity(25, BillingUnit::Hour, 'horas'))->toBe('25 horas');
});

it('preserves fractional hours instead of rounding them away', function (): void {
    $formatter = new InvoiceMoneyFormatter('es', 'EUR', '€');

    // The previous `number_format($q, 0)` turned 3,5 hours into "4".
    expect($formatter->quantity(3.5, BillingUnit::Hour, 'horas'))->toBe('3,50 horas');
});

it('never gives a unit count decimals', function (): void {
    $formatter = new InvoiceMoneyFormatter('en', 'USD', '$');

    expect($formatter->quantity(1, BillingUnit::Unit, ''))->toBe('1')
        ->and($formatter->quantity(2, BillingUnit::Session, 'sessions'))->toBe('2 sessions');
});
