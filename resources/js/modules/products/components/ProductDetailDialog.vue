<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    billingUnitLabel,
    formatDate,
    formatUnitPrice,
    productStatusLabel,
    productStatusVariant,
    productTypeLabel,
} from '../helpers/productPresentation';
import type { Product } from '../types';

/**
 * Read-only detail for one product, opened from the row's "View" action.
 * Everything shown here is already on the list row, so there is no second
 * fetch — the dialog just renders `product` when it is non-null.
 */
const { product = null } = defineProps<{ product?: Product | null }>();

const open = defineModel<boolean>('open', { default: false });

type DetailRow = { label: string; value: string };

const rows = computed<DetailRow[]>(() => {
    const record = product;

    if (!record) {
        return [];
    }

    return [
        {
            label: 'Price',
            value: formatUnitPrice(
                record.price,
                record.currency,
                record.default_unit,
            ),
        },
        { label: 'Billed', value: billingUnitLabel(record.default_unit) },
        { label: 'Client', value: record.client_name ?? '—' },
        {
            label: 'Total hours',
            value: record.total_hours === null ? '—' : String(record.total_hours),
        },
        {
            label: 'Sessions',
            value:
                record.total_sessions === null
                    ? '—'
                    : String(record.total_sessions),
        },
        { label: 'Modality', value: record.modality ?? '—' },
        { label: 'Level', value: record.level },
        { label: 'Language', value: record.language },
        { label: 'Starts', value: formatDate(record.start_date) ?? '—' },
        { label: 'Ends', value: formatDate(record.end_date) ?? '—' },
        { label: 'Slug', value: record.slug },
        { label: 'Created', value: formatDate(record.created_at) ?? '—' },
    ];
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ product?.title ?? 'Product' }}</DialogTitle>
                <DialogDescription>
                    Catalog entry. Edits are audit-logged.
                </DialogDescription>
            </DialogHeader>

            <div v-if="product" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {{ productTypeLabel(product.type) }}
                    </Badge>
                    <Badge :variant="productStatusVariant(product.status)">
                        {{ productStatusLabel(product.status) }}
                    </Badge>
                    <Badge v-if="product.deleted_at" variant="destructive">
                        Deleted
                    </Badge>
                </div>

                <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                    <div v-for="row in rows" :key="row.label" class="text-sm">
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="font-medium">{{ row.value }}</dd>
                    </div>
                </dl>

                <div v-if="product.description" class="text-sm">
                    <p class="pb-1 text-muted-foreground">Description</p>
                    <!-- `whitespace-pre-line`: the session breakdown's line
                         breaks are the content, and the PDF preserves them too. -->
                    <p class="whitespace-pre-line">{{ product.description }}</p>
                </div>

                <div v-if="product.notes" class="text-sm">
                    <p class="pb-1 text-muted-foreground">
                        Internal notes (never printed)
                    </p>
                    <p class="whitespace-pre-line">{{ product.notes }}</p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
