import type { InvoiceItemFormValues } from '../schemas/invoiceFormSchema';
import type { TaxMode } from '../types';

/**
 * The browser's copy of `InvoiceTotalsCalculator::compute()`.
 *
 * It exists so the form can show a running subtotal / tax / total while the
 * operator edits, rather than making them save to find out what the invoice
 * comes to. The server recomputes all three on write and stores *its* numbers —
 * this is a preview, never the value that is persisted, which is why nothing
 * here is ever sent in the payload.
 *
 * The rounding is deliberately identical to the PHP: each line rounded to two
 * decimals before it is summed, the subtotal rounded again, then the tax. Doing
 * it any other way makes the preview disagree with the saved invoice by a cent
 * on long invoices, which is exactly the kind of drift an operator notices and
 * cannot explain.
 */

export type InvoiceTotals = {
    subtotal: number;
    tax_amount: number;
    total: number;
};

/** PHP's `round($value, 2)` — half away from zero, not JS's half-up-toward-∞. */
function round2(value: number): number {
    const scaled = value * 100;
    const rounded = scaled < 0 ? -Math.round(-scaled) : Math.round(scaled);

    return rounded / 100;
}

/** One line's `amount`, as the server will store it. */
export function lineAmount(item: InvoiceItemFormValues): number {
    return round2(round2(item.quantity) * round2(item.unit_price));
}

export function computeInvoiceTotals(
    items: readonly InvoiceItemFormValues[],
    taxMode: TaxMode,
    taxRate: number | null,
): InvoiceTotals {
    const subtotal = round2(
        items.reduce((carry, item) => carry + lineAmount(item), 0),
    );

    const tax_amount =
        taxMode === 'PERCENT' ? round2(subtotal * ((taxRate ?? 0) / 100)) : 0;

    return { subtotal, tax_amount, total: round2(subtotal + tax_amount) };
}
