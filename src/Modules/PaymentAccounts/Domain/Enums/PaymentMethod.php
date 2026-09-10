<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Domain\Enums;

/**
 * How money moves. Deliberately independent of currency — Remitly/USD,
 * bank transfer/EUR and bank transfer/USD are all valid combinations, so the
 * currency lives on the account, never baked into the method.
 */
enum PaymentMethod: string
{
    case Remitly = 'REMITLY';
    case BankTransfer = 'BANK_TRANSFER';
    case Wise = 'WISE';
    case PayPal = 'PAYPAL';
    case Stripe = 'STRIPE';
    case Cash = 'CASH';
    case Other = 'OTHER';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * IBAN / BIC / bank name are only meaningful for a real bank account —
     * a Remitly or PayPal account renders its handle instead.
     */
    public function isBankAccount(): bool
    {
        return $this === self::BankTransfer;
    }

    /**
     * Key into the locale label maps in
     * `Modules\Invoices\Application\Support\InvoicePdfViewAssembler`.
     *
     * Referenced by name, not imported: Domain must not depend on another
     * module's Application layer, and a docblock is no exception.
     */
    public function labelKey(): string
    {
        return 'payment_method_'.strtolower($this->value);
    }
}
