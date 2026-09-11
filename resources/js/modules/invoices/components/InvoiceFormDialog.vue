<script setup lang="ts">
import { AlertCircleIcon, CheckCircle2Icon, SparklesIcon } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { computed, watch } from 'vue';
import {
    AppField,
    fieldErrorMessages,
    FormDialog,
    TextField,
} from '@/common/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useInvoiceForm } from '../composables/useInvoiceForm';
import { useInvoiceFormOptions } from '../composables/useInvoiceFormOptions';
import {
    useInvoiceNumberCheck,
    useNextInvoiceNumber,
} from '../composables/useInvoiceNumber';
import {
    formatMoney,
    paymentMethodLabel,
} from '../helpers/invoicePresentation';
import { computeInvoiceTotals } from '../helpers/invoiceTotals';
import {
    isPaymentMethod,
    isTaxMode,
    PAYMENT_METHOD_VALUES,
    TAX_MODE_VALUES,
} from '../schemas/invoiceFormSchema';
import type { InvoiceDetail } from '../types';
import InvoiceLineItemsField from './InvoiceLineItemsField.vue';

/**
 * One dialog for both create and edit.
 *
 * `invoice` is `null` in create mode; `useInvoiceForm` reads it at submit time
 * to decide between `POST` and `PUT`, so this component only carries the prop
 * through rather than branching on it itself.
 *
 * It is deliberately fed the fetched `InvoiceDetail` and not a list row: the
 * list carries no line items, and seeding an edit form from it would blank
 * every line the operator did not retype.
 */
const { invoice = null, loadingInvoice = false } = defineProps<{
    invoice?: InvoiceDetail | null;
    /** True while the detail record for an edit is still in flight. */
    loadingInvoice?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEditing = computed(() => invoice !== null);

const form = useInvoiceForm({
    open,
    invoice: () => invoice,
    onSuccess: () => {
        open.value = false;
    },
});

// Deferred to the first open — see the `enabled` note in the composable.
const {
    clients,
    services,
    products,
    defaultNotes,
    accountsForCurrency,
    isLoading: optionsLoading,
} = useInvoiceFormOptions(() => open.value);

/*
 * The slices of form state this template reacts to.
 *
 * Read through `useStore` rather than a second `ref` so there is exactly one
 * copy of each value: the totals preview, the payment block's visibility and
 * the number check all derive from what the fields actually hold.
 */
const currency = form.useStore((state) => state.values.currency);
const items = form.useStore((state) => state.values.items);
const taxMode = form.useStore((state) => state.values.tax_mode);
const taxRate = form.useStore((state) => state.values.tax_rate);
const isPaid = form.useStore((state) => state.values.is_paid);
const issueDate = form.useStore((state) => state.values.issue_date);
const invoiceNumber = form.useStore((state) => state.values.invoice_number);
const isSubmitting = form.useStore((state) => state.isSubmitting);

/** The running preview. Recomputed on every keystroke; never sent to the server. */
const totals = computed(() =>
    computeInvoiceTotals(items.value, taxMode.value, taxRate.value),
);

/** The invoice's year is the year it is issued in — that is what numbers it. */
const year = computed<number | null>(() => {
    const parsed = Number(issueDate.value.slice(0, 4));

    return Number.isFinite(parsed) && parsed > 2000 ? parsed : null;
});

const { data: suggestion } = useNextInvoiceNumber(
    () => year.value,
    () => open.value && !isEditing.value,
);

/**
 * Adopt the suggested number, but only into an empty field.
 *
 * Guarding on emptiness is what keeps the suggestion from overwriting a number
 * the operator has already typed when they later change the issue date — the
 * year changes, a fresh suggestion arrives, and silently renumbering their
 * invoice underneath them would be indefensible. The "Use 014/2026" button
 * below is how they opt into the new one.
 */
watch(suggestion, (next) => {
    if (
        next &&
        !isEditing.value &&
        form.getFieldValue('invoice_number') === ''
    ) {
        form.setFieldValue('invoice_number', next.invoice_number);
    }
});

/**
 * `throttle:30,1` on the endpoint, so the keystrokes are debounced before they
 * become requests; the query itself then caches each distinct number for a
 * minute (see `useInvoiceNumberCheck`).
 */
const debouncedNumber = refDebounced(invoiceNumber, 400);

const { isAvailable, conflict } = useInvoiceNumberCheck(
    () => debouncedNumber.value,
    () => invoice?.uuid ?? null,
);

/** The rails that can settle the currency currently selected. */
const paymentAccounts = computed(() =>
    accountsForCurrency(() => currency.value),
);

/**
 * Seed the notes from the company default, for a new invoice only.
 *
 * An existing invoice keeps whatever was saved on it even when the company
 * default has since changed: the notes are part of the document that was
 * issued, not a live reference to a setting.
 */
watch([open, defaultNotes], ([isOpen, notes]) => {
    if (
        isOpen &&
        !isEditing.value &&
        notes &&
        form.getFieldValue('notes') === ''
    ) {
        form.setFieldValue('notes', notes);
    }
});

/** reka `Select` has no empty-string item, so "not set" gets a sentinel. */
const NONE = '__none__';
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="isEditing ? 'Edit invoice' : 'New invoice'"
        :description="
            isEditing
                ? 'Totals are recalculated on save from the lines below.'
                : 'The number is suggested from this year’s sequence — change it if you need to.'
        "
        submit-label="Save invoice"
        content-class="sm:max-w-4xl"
    >
        <div
            v-if="loadingInvoice || optionsLoading"
            class="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground"
        >
            <Spinner class="size-4" />
            Loading invoice data…
        </div>

        <div v-else class="grid gap-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="client_uuid" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Client"
                        required
                        description="Billing details are copied from the client record onto the PDF."
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value || NONE"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        value === NONE ? '' : String(value),
                                    )
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Choose a client" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="client in clients"
                                    :key="client.uuid"
                                    :value="client.uuid"
                                >
                                    {{ client.client_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>

                <form.Field name="invoice_number" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Invoice number"
                        required
                        description="A per-year sequence — 014/2026. Typing 14 is enough."
                        #default="{ control }"
                    >
                        <div class="flex flex-col gap-1.5">
                            <Input
                                v-bind="control"
                                :model-value="field.state.value"
                                placeholder="014/2026"
                                @update:model-value="
                                    (value) => field.handleChange(String(value))
                                "
                                @blur="field.handleBlur"
                            />

                            <p
                                v-if="isAvailable === true"
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <CheckCircle2Icon
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                That number is free.
                            </p>

                            <p
                                v-else-if="isAvailable === false"
                                class="flex items-start gap-1.5 text-xs text-destructive"
                            >
                                <AlertCircleIcon
                                    class="mt-0.5 size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    Already used by
                                    {{ conflict?.client_name }}
                                    <template v-if="conflict?.is_suspended">
                                        (on a suspended invoice — the number
                                        stays taken).
                                    </template>
                                </span>
                            </p>

                            <Button
                                v-if="
                                    !isEditing &&
                                    suggestion &&
                                    field.state.value !==
                                        suggestion.invoice_number
                                "
                                type="button"
                                variant="link"
                                size="sm"
                                class="h-auto justify-start p-0"
                                @click="
                                    field.handleChange(
                                        suggestion.invoice_number,
                                    )
                                "
                            >
                                <SparklesIcon
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                Use {{ suggestion.invoice_number }}
                            </Button>
                        </div>
                    </AppField>
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <form.Field name="issue_date" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Issue date"
                        type="date"
                        required
                    />
                </form.Field>

                <form.Field name="due_date" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Due date"
                        type="date"
                        required
                    />
                </form.Field>

                <form.Field name="currency" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Currency"
                        required
                        placeholder="EUR"
                        description="Three-letter code."
                    />
                </form.Field>
            </div>

            <Separator />

            <form.Field name="items" #default="{ field }">
                <div class="flex flex-col gap-2">
                    <InvoiceLineItemsField
                        :model-value="field.state.value"
                        :currency="currency"
                        :services="services"
                        :products="products"
                        :disabled="isSubmitting"
                        @update:model-value="
                            (value) => field.handleChange(value)
                        "
                    />

                    <!--
                        The array field's own errors — "needs at least one
                        line", plus anything Zod reports against a specific
                        line. `InvoiceLineItemsField` renders no error surface
                        of its own, so this is where they land.
                    -->
                    <p
                        v-for="message in fieldErrorMessages(field)"
                        :key="message"
                        class="text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                </div>
            </form.Field>

            <Separator />

            <div class="grid gap-4 sm:grid-cols-3">
                <form.Field name="tax_mode" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Tax"
                        required
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) =>
                                    isTaxMode(value) &&
                                    field.handleChange(value)
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="mode in TAX_MODE_VALUES"
                                    :key="mode"
                                    :value="mode"
                                >
                                    {{
                                        mode === 'EXEMPT'
                                            ? 'Exempt'
                                            : 'Percentage'
                                    }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>

                <form.Field name="tax_rate" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Rate (%)"
                        :description="
                            taxMode === 'EXEMPT'
                                ? 'Ignored while the invoice is exempt.'
                                : undefined
                        "
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0"
                            max="100"
                            :disabled="taxMode === 'EXEMPT'"
                            :model-value="field.state.value ?? ''"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        value === '' ? null : Number(value),
                                    )
                            "
                            @blur="field.handleBlur"
                        />
                    </AppField>
                </form.Field>

                <form.Field name="tax_label" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Tax label"
                        required
                        placeholder="IVA"
                        description="Printed beside the tax line."
                    />
                </form.Field>
            </div>

            <dl
                class="ml-auto grid w-full max-w-xs gap-1 rounded-xl border border-border bg-muted/40 p-3 text-sm"
            >
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">Subtotal</dt>
                    <dd class="tabular-nums">
                        {{ formatMoney(totals.subtotal, currency) }}
                    </dd>
                </div>
                <div class="flex items-baseline justify-between gap-4">
                    <dt class="text-muted-foreground">Tax</dt>
                    <dd class="tabular-nums">
                        {{ formatMoney(totals.tax_amount, currency) }}
                    </dd>
                </div>
                <Separator class="my-1" />
                <div
                    class="flex items-baseline justify-between gap-4 font-semibold"
                >
                    <dt>Total</dt>
                    <dd class="tabular-nums">
                        {{ formatMoney(totals.total, currency) }}
                    </dd>
                </div>
                <p class="pt-1 text-xs text-muted-foreground">
                    A preview — the server recomputes these on save.
                </p>
            </dl>

            <Separator />

            <form.Field name="is_paid" #default="{ field }">
                <AppField
                    :field="field"
                    label="Already paid"
                    description="Turning this on prints the PAYMENT RECEIVED block on the PDF."
                    orientation="horizontal"
                    #default="{ control }"
                >
                    <Switch
                        v-bind="control"
                        :model-value="field.state.value"
                        @update:model-value="
                            (value) => field.handleChange(Boolean(value))
                        "
                    />
                </AppField>
            </form.Field>

            <div v-if="isPaid" class="grid gap-4 sm:grid-cols-2">
                <form.Field name="payment_method" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Method"
                        required
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value ?? NONE"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        isPaymentMethod(value) ? value : null,
                                    )
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Choose a method" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="method in PAYMENT_METHOD_VALUES"
                                    :key="method"
                                    :value="method"
                                >
                                    {{ paymentMethodLabel(method) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>

                <form.Field name="payment_account_uuid" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Settled into"
                        :description="`Rails that accept ${currency}. Choosing one prints its real details instead of a bare method name.`"
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value ?? NONE"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        value === NONE ? null : String(value),
                                    )
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Not specified" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">
                                    Not specified
                                </SelectItem>
                                <SelectItem
                                    v-for="account in paymentAccounts"
                                    :key="account.uuid"
                                    :value="account.uuid"
                                >
                                    {{ account.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>

                <form.Field name="payment_date" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Payment date"
                        required
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            type="date"
                            :model-value="field.state.value ?? ''"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        value === '' ? null : String(value),
                                    )
                            "
                            @blur="field.handleBlur"
                        />
                    </AppField>
                </form.Field>

                <form.Field name="amount_received" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Amount received"
                        required
                        :description="`Total is ${formatMoney(totals.total, currency)}.`"
                        #default="{ control }"
                    >
                        <Input
                            v-bind="control"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0"
                            :model-value="field.state.value ?? ''"
                            @update:model-value="
                                (value) =>
                                    field.handleChange(
                                        value === '' ? null : Number(value),
                                    )
                            "
                            @blur="field.handleBlur"
                        />
                    </AppField>
                </form.Field>

                <form.Field name="transfer_number" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Reference"
                        placeholder="MTCN / transfer reference"
                        description="Optional — printed beside the method."
                    />
                </form.Field>
            </div>

            <Separator />

            <form.Field name="notes" #default="{ field }">
                <TextField
                    :field="field"
                    label="Notes"
                    multiline
                    :rows="3"
                    description="Printed under the totals. Seeded from the company default on a new invoice."
                />
            </form.Field>

            <form.Field name="additional_notes" #default="{ field }">
                <TextField
                    :field="field"
                    label="Additional notes"
                    multiline
                    :rows="2"
                    description="A second block under the notes — VAT wording, payment terms."
                />
            </form.Field>
        </div>
    </FormDialog>
</template>
