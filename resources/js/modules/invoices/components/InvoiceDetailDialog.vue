<script setup lang="ts">
import { DownloadIcon } from '@lucide/vue';
import { computed } from 'vue';
import PermissionGuard from '@/common/auth/PermissionGuard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { pdf } from '@/routes/invoices/admin';
import {
    formatDate,
    formatLineRate,
    formatMoney,
    isOverdue,
    itemKindLabel,
    paymentMethodLabel,
} from '../helpers/invoicePresentation';
import type { InvoiceDetail } from '../types';
import InvoicePaidBadge from './InvoicePaidBadge.vue';
import InvoiceStatusBadge from './InvoiceStatusBadge.vue';

/**
 * Read-only detail for one invoice, opened from the row's "View" action.
 *
 * Unlike the product dialog next door this one cannot render from the list row:
 * `InvoiceListItemData` carries no `items`, no tax breakdown, no notes and no
 * payment snapshot. The page owns the fetch (`useInvoice`) and passes the
 * result down, so the same request also seeds the edit form when the operator
 * moves straight from viewing to editing.
 */
const { invoice = null, loading = false } = defineProps<{
    invoice?: InvoiceDetail | null;
    loading?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const overdue = computed(() => (invoice === null ? false : isOverdue(invoice)));

type DetailRow = { label: string; value: string };

const billing = computed<DetailRow[]>(() => {
    const record = invoice;

    if (!record) {
        return [];
    }

    return [
        { label: 'Client', value: record.client_name ?? '—' },
        { label: 'Issued', value: formatDate(record.issue_date) ?? '—' },
        { label: 'Due', value: formatDate(record.due_date) ?? '—' },
        {
            label: 'Tax',
            value:
                record.tax_mode === 'PERCENT'
                    ? `${record.tax_label} ${record.tax_rate ?? 0}%`
                    : `${record.tax_label} — exempt`,
        },
        { label: 'Catalog product', value: record.product_title ?? '—' },
        { label: 'Created', value: formatDate(record.created_at) ?? '—' },
    ];
});

/** Only rendered when the invoice is settled — an empty block says nothing. */
const payment = computed<DetailRow[]>(() => {
    const record = invoice;

    if (!record?.is_paid) {
        return [];
    }

    return [
        { label: 'Method', value: paymentMethodLabel(record.payment_method) },
        {
            label: 'Settled into',
            // Masked on the server: the full IBAN never reaches a screen that
            // gets screen-shared (`InvoiceDetailData`).
            value:
                record.payment_account_label === null
                    ? '—'
                    : [
                          record.payment_account_label,
                          record.payment_account_masked,
                      ]
                          .filter(Boolean)
                          .join(' · '),
        },
        { label: 'Paid on', value: formatDate(record.payment_date) ?? '—' },
        {
            label: 'Amount received',
            value:
                record.amount_received === null
                    ? '—'
                    : formatMoney(record.amount_received, record.currency),
        },
        { label: 'Reference', value: record.transfer_number ?? '—' },
    ];
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ invoice?.invoice_number ?? 'Invoice' }}
                </DialogTitle>
                <DialogDescription>
                    Issued document. Edits are audit-logged.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="loading"
                class="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"
            >
                <Spinner class="size-4" />
                Loading invoice…
            </div>

            <div
                v-else-if="invoice"
                class="max-h-[65vh] space-y-5 overflow-y-auto pr-1"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <InvoicePaidBadge
                        :is-paid="invoice.is_paid"
                        :overdue="overdue"
                    />
                    <InvoiceStatusBadge :deleted-at="invoice.deleted_at" />
                </div>

                <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                    <div
                        v-for="row in billing"
                        :key="row.label"
                        class="flex items-baseline justify-between gap-4 border-b border-border/60 py-1"
                    >
                        <dt class="text-sm text-muted-foreground">
                            {{ row.label }}
                        </dt>
                        <dd class="text-sm font-medium">{{ row.value }}</dd>
                    </div>
                </dl>

                <section class="space-y-2">
                    <h3 class="text-sm font-medium">Line items</h3>

                    <ul class="space-y-2">
                        <li
                            v-for="(item, index) in invoice.items"
                            :key="`${item.sort_order}-${index}`"
                            class="rounded-lg border border-border bg-muted/30 p-3"
                        >
                            <div
                                class="flex items-baseline justify-between gap-4"
                            >
                                <span class="font-medium">
                                    {{ item.title }}
                                </span>
                                <span class="shrink-0 tabular-nums">
                                    {{
                                        formatMoney(
                                            item.amount,
                                            invoice.currency,
                                        )
                                    }}
                                </span>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                {{ itemKindLabel(item.kind) }} ·
                                {{
                                    formatLineRate(
                                        item.quantity,
                                        item.unit_price,
                                        item.unit,
                                        invoice.currency,
                                    )
                                }}
                            </p>

                            <!--
                                Rendered as text, never `v-html`: the column is
                                free-form operator input that goes straight onto
                                a PDF, and `whitespace-pre-line` is enough to
                                keep the session breakdown's line breaks.
                            -->
                            <p
                                v-if="item.description"
                                class="mt-1 text-xs whitespace-pre-line text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </li>
                    </ul>
                </section>

                <dl class="ml-auto grid w-full max-w-xs gap-1 text-sm">
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-muted-foreground">Subtotal</dt>
                        <dd class="tabular-nums">
                            {{
                                formatMoney(invoice.subtotal, invoice.currency)
                            }}
                        </dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ invoice.tax_label }}
                        </dt>
                        <dd class="tabular-nums">
                            {{
                                formatMoney(
                                    invoice.tax_amount,
                                    invoice.currency,
                                )
                            }}
                        </dd>
                    </div>
                    <Separator class="my-1" />
                    <div
                        class="flex items-baseline justify-between gap-4 font-semibold"
                    >
                        <dt>Total</dt>
                        <dd class="tabular-nums">
                            {{ formatMoney(invoice.total, invoice.currency) }}
                        </dd>
                    </div>
                </dl>

                <section v-if="payment.length > 0" class="space-y-2">
                    <h3 class="text-sm font-medium">Payment received</h3>
                    <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div
                            v-for="row in payment"
                            :key="row.label"
                            class="flex items-baseline justify-between gap-4 border-b border-border/60 py-1"
                        >
                            <dt class="text-sm text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="text-sm font-medium">{{ row.value }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="invoice.notes || invoice.additional_notes">
                    <h3 class="mb-1 text-sm font-medium">Notes</h3>
                    <p
                        v-if="invoice.notes"
                        class="text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ invoice.notes }}
                    </p>
                    <p
                        v-if="invoice.additional_notes"
                        class="mt-2 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ invoice.additional_notes }}
                    </p>
                </section>
            </div>

            <DialogFooter>
                <PermissionGuard permission="EXPORT_INVOICES">
                    <!--
                        A plain link, not a fetch: the endpoint streams a PDF
                        behind session auth, so letting the browser navigate to
                        it in a new tab is both simpler and the only way the
                        native download UI appears.
                    -->
                    <Button v-if="invoice" as-child variant="outline">
                        <a
                            :href="pdf.url(invoice.uuid)"
                            target="_blank"
                            rel="noopener"
                        >
                            <DownloadIcon class="size-4" aria-hidden="true" />
                            Download PDF
                        </a>
                    </Button>
                </PermissionGuard>

                <Button variant="outline" @click="open = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
