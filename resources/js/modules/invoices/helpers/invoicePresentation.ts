import type {
    BillingUnit,
    Currency,
    InvoiceItemKind,
    PaymentMethod,
    ProductType,
} from '../types';

/**
 * Row-derived display values shared by the table, the detail dialog, the form's
 * totals preview and the delete confirmations, so "how an invoice reads" is
 * decided once.
 *
 * Nothing here fetches or mutates: every function is pure and takes the row it
 * describes, which is what lets the form reuse them on values that have not
 * been persisted yet.
 */

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

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

/**
 * `1234.5` + `'EUR'` → `€1,234.50`.
 *
 * Falls back to a plain amount with the code appended when `Intl` cannot format
 * the currency: the server now only accepts EUR, USD and GBP, but a row saved
 * before that rule — or the empty currency of a form still being filled in —
 * must still render, because a thrown `RangeError` inside a table cell would
 * blank the whole row.
 */
export function formatMoney(amount: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount)} ${currency}`.trim();
    }
}

const CURRENCY_LABELS: Record<Currency, string> = {
    EUR: 'EUR — Euro (€)',
    USD: 'USD — US dollar ($)',
    GBP: 'GBP — Pound sterling (£)',
};

/**
 * How the currency picker names a code. A `Record` over the generated union, so
 * a currency added on the server fails the build here until it has a label.
 */
export function currencyLabel(currency: Currency): string {
    return CURRENCY_LABELS[currency];
}

/**
 * The fields these helpers actually read.
 *
 * Structural rather than `InvoiceListItem`, so the detail dialog — which holds
 * an `InvoiceDetail`, a wider shape — can call the same functions without a
 * cast, and so can the form's totals preview on values that are not yet a row.
 */
export type InvoiceLike = {
    invoice_number: string;
    client_name: string | null;
    due_date: string;
    is_paid: boolean;
    deleted_at: string | null;
};

/** How a row names itself in a confirmation list: `014/2026 — Acme Ltd`. */
export function invoiceLabel(invoice: InvoiceLike): string {
    return invoice.client_name
        ? `${invoice.invoice_number} — ${invoice.client_name}`
        : invoice.invoice_number;
}

const ITEM_KIND_LABELS: Record<InvoiceItemKind, string> = {
    SERVICE: 'Service',
    COURSE: 'Course',
    VIDEO: 'Video',
    CUSTOM: 'Custom',
};

export function itemKindLabel(kind: InvoiceItemKind): string {
    return ITEM_KIND_LABELS[kind];
}

/**
 * Mirrors `InvoiceItemKind::requiresProduct()`.
 *
 * The server rejects a COURSE or VIDEO line with no `product_uuid`
 * (`required_if:items.*.kind,…`); saying so in the browser turns a 422 into a
 * disabled state the operator can see before they submit.
 */
export function kindRequiresProduct(kind: InvoiceItemKind): boolean {
    return kind === 'COURSE' || kind === 'VIDEO';
}

/**
 * Mirrors `InvoiceItemKind::forProductType()`: recorded material bills as a
 * VIDEO line, every live format — course, workshop, mentoring — as a COURSE
 * line. The server derives a line's kind from its product whatever the request
 * says, so the form uses this to show what will actually be saved.
 */
export function kindForProductType(type: ProductType): InvoiceItemKind {
    return type === 'VIDEO_COURSE' ? 'VIDEO' : 'COURSE';
}

const UNIT_LABELS: Record<BillingUnit, string> = {
    UNIT: 'unit',
    HOUR: 'hour',
    SESSION: 'session',
    DAY: 'day',
    MONTH: 'month',
};

export function billingUnitLabel(unit: BillingUnit): string {
    return UNIT_LABELS[unit];
}

/** `25 hours × €52.00` — the line as the PDF phrases it. */
export function formatLineRate(
    quantity: number,
    unitPrice: number,
    unit: BillingUnit,
    currency: string,
): string {
    const noun =
        unit === 'UNIT'
            ? ''
            : ` ${UNIT_LABELS[unit]}${quantity === 1 ? '' : 's'}`;

    return `${formatQuantity(quantity)}${noun} × ${formatMoney(unitPrice, currency)}`;
}

/** Trims the trailing zeros a quantity rarely needs: `2.50` → `2.5`, `3.00` → `3`. */
export function formatQuantity(quantity: number): string {
    return new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 2,
    }).format(quantity);
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

export function paymentMethodLabel(method: PaymentMethod | null): string {
    return method === null ? '—' : METHOD_LABELS[method];
}

export function paidVariant(isPaid: boolean): BadgeVariant {
    return isPaid ? 'default' : 'outline';
}

/**
 * Whether an unpaid invoice is past its due date.
 *
 * Compared as `YYYY-MM-DD` strings rather than `Date`s on purpose: `due_date`
 * is a calendar date with no time and no zone, and `new Date('2026-06-03')`
 * parses as midnight UTC — which is the previous day for anyone west of
 * Greenwich, so an invoice would read as overdue a day early.
 */
export function isOverdue(invoice: InvoiceLike): boolean {
    if (invoice.is_paid || invoice.deleted_at !== null) {
        return false;
    }

    return invoice.due_date < todayIso();
}

/** Today as `YYYY-MM-DD` in the viewer's own zone. */
export function todayIso(): string {
    const now = new Date();

    return [
        String(now.getFullYear()),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
}

/** `todayIso()` shifted by whole days — used for the default due date. */
export function isoPlusDays(iso: string, days: number): string {
    const [year, month, day] = iso.split('-').map(Number);
    // Constructed in local time and read back the same way, so the arithmetic
    // never crosses a zone boundary.
    const shifted = new Date(year, month - 1, day + days);

    return [
        String(shifted.getFullYear()),
        String(shifted.getMonth() + 1).padStart(2, '0'),
        String(shifted.getDate()).padStart(2, '0'),
    ].join('-');
}
