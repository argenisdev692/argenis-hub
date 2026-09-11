<script setup lang="ts">
import {
    ChevronDownIcon,
    ChevronUpIcon,
    PlusIcon,
    Trash2Icon,
} from '@lucide/vue';
import { computed, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    billingUnitLabel,
    formatMoney,
    itemKindLabel,
    kindRequiresProduct,
} from '../helpers/invoicePresentation';
import { lineAmount } from '../helpers/invoiceTotals';
import type { InvoiceItemFormValues } from '../schemas/invoiceFormSchema';
import {
    BILLING_UNIT_VALUES,
    emptyInvoiceItem,
    INVOICE_ITEM_KIND_VALUES,
    isBillingUnit,
    isInvoiceItemKind,
} from '../schemas/invoiceFormSchema';
import type {
    BillingUnit,
    InvoiceItemKind,
    InvoiceProductOption,
    InvoiceServiceOption,
} from '../types';

/**
 * The line-item editor.
 *
 * Extracted from `InvoiceFormDialog` because it is the only genuinely complex
 * part of that form: everything else there is a flat field bound to a scalar,
 * while a line carries a catalog link that rewrites four of its own siblings
 * when it changes. Keeping that in its own component is what stops the dialog
 * from being 600 lines of template with the interesting logic buried in it.
 *
 * It owns no state. The whole array arrives through `v-model` and every edit
 * emits a fresh array, which is what lets the parent hand the value straight to
 * one TanStack `form.Field` — the codebase's idiom for arrays (see
 * `PortfolioFormSheet`'s gallery) rather than TanStack's nested `mode="array"`.
 */
const {
    currency,
    services = [],
    products = [],
    maxItems = 50,
    disabled = false,
} = defineProps<{
    /** Drives the money formatting only — the lines carry no currency of their own. */
    currency: string;
    services?: InvoiceServiceOption[];
    products?: InvoiceProductOption[];
    maxItems?: number;
    disabled?: boolean;
}>();

const items = defineModel<InvoiceItemFormValues[]>({ default: () => [] });

const headingId = useId();

const canAdd = computed(() => items.value.length < maxItems);

/** Replaces one line, leaving the rest of the array untouched. */
function patch(index: number, changes: Partial<InvoiceItemFormValues>): void {
    items.value = items.value.map((item, position) =>
        position === index ? { ...item, ...changes } : item,
    );
}

function addLine(): void {
    if (!canAdd.value) {
        return;
    }

    items.value = [...items.value, emptyInvoiceItem(items.value.length)];
}

/**
 * The last line is never removable: `items` is `required|min:1` on the server,
 * and an invoice with no lines cannot be priced. Disabling the button says so
 * before the submit does.
 */
function removeLine(index: number): void {
    if (items.value.length <= 1) {
        return;
    }

    items.value = items.value.filter((_item, position) => position !== index);
}

/**
 * Reordering by button rather than by drag.
 *
 * `sort_order` is what decides the order lines print on the PDF, and it is
 * renumbered from the array index on submit. Buttons were chosen over the
 * project's `SortableList` for two reasons: a drag handle is unusable from a
 * keyboard without a parallel control anyway, and `SortableList` requires each
 * row to carry an `id`, which would mean a UI-only field inside the schema that
 * mirrors the backend DTO.
 */
function move(index: number, offset: -1 | 1): void {
    const target = index + offset;

    if (target < 0 || target >= items.value.length) {
        return;
    }

    const next = [...items.value];
    [next[index], next[target]] = [next[target], next[index]];
    items.value = next;
}

/**
 * Switching kind clears the catalog link the previous kind carried.
 *
 * A line that was a COURSE keeps `product_uuid` pointing at a product it no
 * longer bills unless it is cleared here, and `toInvoiceWritePayload` would
 * happily send it — the server accepts a `product_uuid` on a CUSTOM line, so
 * nothing would reject the stale reference; it would just quietly attach the
 * wrong catalog row to the invoice.
 */
function onKindChange(index: number, value: unknown): void {
    if (!isInvoiceItemKind(value)) {
        return;
    }

    patch(index, { kind: value, service_uuid: null, product_uuid: null });
}

/** Picking a service fills the line's wording; the price stays the operator's. */
function onServiceChange(index: number, uuid: string | null): void {
    const service = services.find((option) => option.uuid === uuid) ?? null;

    patch(index, {
        service_uuid: service?.uuid ?? null,
        ...(service
            ? {
                  title: service.name,
                  description: service.description ?? '',
              }
            : {}),
    });
}

/**
 * Picking a product fills the line from the catalog: wording, unit, price, and
 * — for an hourly product — the hours it is sold in.
 *
 * `price` arrives as `number | string` because the column is a DECIMAL and the
 * JSON driver hands those back as strings on some connections; `Number()` here
 * is the one place that has to care.
 */
function onProductChange(index: number, uuid: string | null): void {
    const product = products.find((option) => option.uuid === uuid) ?? null;

    if (!product) {
        patch(index, { product_uuid: null });

        return;
    }

    const unit = product.default_unit;
    const quantity =
        unit === 'HOUR' &&
        product.total_hours !== null &&
        product.total_hours > 0
            ? product.total_hours
            : 1;

    patch(index, {
        product_uuid: product.uuid,
        title: product.title,
        description: product.description ?? '',
        unit,
        unit_price: Number(product.price),
        quantity,
    });
}

function onUnitChange(index: number, value: unknown): void {
    if (isBillingUnit(value)) {
        patch(index, { unit: value });
    }
}

/**
 * `<input type="number">` yields `''` while the field is being cleared, and
 * `Number('')` is 0 — which would silently price a line at zero mid-keystroke.
 * A blank is held at the schema's floor instead so the running total never
 * shows a number the operator did not type.
 */
function toQuantity(raw: string | number): number {
    const parsed = Number(raw);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0.01;
}

function toPrice(raw: string | number): number {
    const parsed = Number(raw);

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
}

/** reka `Select` has no empty-string item, so "no catalog row" gets a sentinel. */
const NONE = '__none__';

/** The kinds are a closed set; `unit` is offered for every one of them. */
const kinds: readonly InvoiceItemKind[] = INVOICE_ITEM_KIND_VALUES;
const units: readonly BillingUnit[] = BILLING_UNIT_VALUES;
</script>

<template>
    <section :aria-labelledby="headingId" class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 :id="headingId" class="text-sm font-medium">Line items</h3>
                <p class="text-xs text-muted-foreground">
                    Each line prints as one row on the invoice PDF.
                </p>
            </div>

            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="disabled || !canAdd"
                @click="addLine"
            >
                <PlusIcon class="size-4" aria-hidden="true" />
                Add line
            </Button>
        </div>

        <ol class="flex flex-col gap-3">
            <li
                v-for="(item, index) in items"
                :key="index"
                class="rounded-xl border border-border bg-card p-3"
            >
                <div class="mb-3 flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-muted-foreground">
                        Line {{ index + 1 }}
                    </span>

                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="disabled || index === 0"
                            :aria-label="`Move line ${index + 1} up`"
                            @click="move(index, -1)"
                        >
                            <ChevronUpIcon class="size-4" aria-hidden="true" />
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="disabled || index === items.length - 1"
                            :aria-label="`Move line ${index + 1} down`"
                            @click="move(index, 1)"
                        >
                            <ChevronDownIcon
                                class="size-4"
                                aria-hidden="true"
                            />
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="disabled || items.length <= 1"
                            :aria-label="`Remove line ${index + 1}`"
                            :title="
                                items.length <= 1
                                    ? 'An invoice needs at least one line.'
                                    : undefined
                            "
                            @click="removeLine(index)"
                        >
                            <Trash2Icon
                                class="size-4 text-destructive"
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label :for="`${headingId}-kind-${index}`">Kind</Label>
                        <Select
                            :model-value="item.kind"
                            :disabled="disabled"
                            @update:model-value="
                                (value) => onKindChange(index, value)
                            "
                        >
                            <SelectTrigger
                                :id="`${headingId}-kind-${index}`"
                                class="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="kind in kinds"
                                    :key="kind"
                                    :value="kind"
                                >
                                    {{ itemKindLabel(kind) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div v-if="item.kind === 'SERVICE'" class="grid gap-1.5">
                        <Label :for="`${headingId}-service-${index}`">
                            Service
                        </Label>
                        <Select
                            :model-value="item.service_uuid ?? NONE"
                            :disabled="disabled"
                            @update:model-value="
                                (value) =>
                                    onServiceChange(
                                        index,
                                        value === NONE ? null : String(value),
                                    )
                            "
                        >
                            <SelectTrigger
                                :id="`${headingId}-service-${index}`"
                                class="w-full"
                            >
                                <SelectValue placeholder="No service" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE"
                                    >No service</SelectItem
                                >
                                <SelectItem
                                    v-for="service in services"
                                    :key="service.uuid"
                                    :value="service.uuid"
                                >
                                    {{ service.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div
                        v-else-if="kindRequiresProduct(item.kind)"
                        class="grid gap-1.5"
                    >
                        <Label :for="`${headingId}-product-${index}`">
                            Product
                            <span class="text-destructive" aria-hidden="true">
                                *
                            </span>
                        </Label>
                        <Select
                            :model-value="item.product_uuid ?? NONE"
                            :disabled="disabled"
                            @update:model-value="
                                (value) =>
                                    onProductChange(
                                        index,
                                        value === NONE ? null : String(value),
                                    )
                            "
                        >
                            <SelectTrigger
                                :id="`${headingId}-product-${index}`"
                                class="w-full"
                                :aria-invalid="item.product_uuid === null"
                            >
                                <SelectValue placeholder="Choose a product" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">
                                    Choose a product
                                </SelectItem>
                                <SelectItem
                                    v-for="product in products"
                                    :key="product.uuid"
                                    :value="product.uuid"
                                >
                                    {{ product.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="item.product_uuid === null"
                            class="text-xs text-destructive"
                        >
                            A {{ itemKindLabel(item.kind).toLowerCase() }} line
                            must reference a catalog product.
                        </p>
                    </div>
                </div>

                <div class="mt-3 grid gap-1.5">
                    <Label :for="`${headingId}-title-${index}`">
                        Title
                        <span class="text-destructive" aria-hidden="true">
                            *
                        </span>
                    </Label>
                    <Input
                        :id="`${headingId}-title-${index}`"
                        :model-value="item.title"
                        :disabled="disabled"
                        placeholder="GitHub Copilot para Desarrolladores Web"
                        @update:model-value="
                            (value) => patch(index, { title: String(value) })
                        "
                    />
                </div>

                <div class="mt-3 grid gap-1.5">
                    <Label :for="`${headingId}-description-${index}`">
                        Description
                    </Label>
                    <Textarea
                        :id="`${headingId}-description-${index}`"
                        :model-value="item.description"
                        :rows="3"
                        :disabled="disabled"
                        placeholder="Formación completa en 8 sesiones:"
                        @update:model-value="
                            (value) =>
                                patch(index, { description: String(value) })
                        "
                    />
                    <p class="text-xs text-muted-foreground">
                        Line breaks are preserved on the PDF — use them for the
                        session breakdown.
                    </p>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-4">
                    <div class="grid gap-1.5">
                        <Label :for="`${headingId}-quantity-${index}`">
                            Quantity
                        </Label>
                        <Input
                            :id="`${headingId}-quantity-${index}`"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0.01"
                            :model-value="item.quantity"
                            :disabled="disabled"
                            @update:model-value="
                                (value) =>
                                    patch(index, {
                                        quantity: toQuantity(value),
                                    })
                            "
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <Label :for="`${headingId}-unit-${index}`">
                            Priced per
                        </Label>
                        <Select
                            :model-value="item.unit"
                            :disabled="disabled"
                            @update:model-value="
                                (value) => onUnitChange(index, value)
                            "
                        >
                            <SelectTrigger
                                :id="`${headingId}-unit-${index}`"
                                class="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="unit in units"
                                    :key="unit"
                                    :value="unit"
                                    class="capitalize"
                                >
                                    {{ billingUnitLabel(unit) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-1.5">
                        <Label :for="`${headingId}-price-${index}`">
                            Unit price
                        </Label>
                        <Input
                            :id="`${headingId}-price-${index}`"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0"
                            :model-value="item.unit_price"
                            :disabled="disabled"
                            @update:model-value="
                                (value) =>
                                    patch(index, { unit_price: toPrice(value) })
                            "
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium">Amount</span>
                        <output
                            class="flex h-9 items-center justify-end rounded-md border border-border bg-muted/40 px-3 text-sm font-medium tabular-nums"
                        >
                            {{ formatMoney(lineAmount(item), currency) }}
                        </output>
                    </div>
                </div>
            </li>
        </ol>
    </section>
</template>
