import type { PaymentAccount, PaymentMethod } from '../types';

/**
 * Row-derived display values shared by the table, the detail dialog and the
 * delete confirmations, so "how a rail is labelled" is decided once.
 */

/** ISO8601 → "3 Jun 2026", or `null` when there is no timestamp. */
export function formatDate(iso: string | null): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

const METHOD_LABELS: Record<PaymentMethod, string> = {
    REMITLY: 'Remitly',
    BANK_TRANSFER: 'Bank transfer',
    WISE: 'Wise',
    PAYPAL: 'PayPal',
    STRIPE: 'Stripe',
    CASH: 'Cash',
    OTHER: 'Other',
};

export function paymentMethodLabel(method: PaymentMethod): string {
    return METHOD_LABELS[method];
}

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

export function paymentMethodVariant(method: PaymentMethod): BadgeVariant {
    return method === 'BANK_TRANSFER' ? 'default' : 'outline';
}

/** "Any currency" is a real state, not a missing value — say so explicitly. */
export function currencyLabel(currency: string | null): string {
    return currency ?? 'Any currency';
}

/**
 * The identifier, masked to its last four characters.
 *
 * The full IBAN is never rendered in a list or a detail pane: those screens get
 * screen-shared and screenshotted, and the operator only ever needs enough to
 * tell two rails apart. The same masking is applied server-side in the export
 * and in `InvoiceDetailData`.
 */
export function maskedIdentifier(account: PaymentAccount): string {
    const value = (account.iban ?? account.account_number ?? '').trim();

    if (value === '') {
        return account.holder_email ?? account.holder_phone ?? '—';
    }

    return value.length <= 4
        ? '•'.repeat(value.length)
        : `•••• ${value.slice(-4)}`;
}
