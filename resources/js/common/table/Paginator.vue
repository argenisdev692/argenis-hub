<script setup lang="ts">
import { computed, useId } from 'vue';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { PaginationMeta } from './types';

const {
    meta,
    disabled = false,
    siblingCount = 2,
    label = 'records',
    perPageOptions = [15, 30, 50],
    class: className,
} = defineProps<{
    meta: PaginationMeta;
    disabled?: boolean;
    /**
     * Page links shown either side of the current one. The default of 2 yields
     * the five-link sliding window the design calls for once the edges are
     * pinned, and collapses to fewer links near the ends where reka clamps it.
     */
    siblingCount?: number;
    /** Plural noun for the counter — "records", "clients", "invoices". */
    label?: string;
    /** Page-size choices offered once a page binds `v-model:per-page`. */
    perPageOptions?: readonly number[];
    class?: string;
}>();

const page = defineModel<number>('page', { required: true });

/**
 * Opt-in page size. Left unbound the control stays out of the DOM entirely, so
 * every existing page renders byte-identical output until it opts in.
 */
const perPage = defineModel<number>('perPage');

const perPageLabelId = useId();

/**
 * Checked narrowing for the select payload (`AcceptableValue` is wider than
 * `number`): a value outside the offered options is ignored rather than
 * written into the filters, where it would 422 the list query.
 */
function onPerPageChange(value: unknown): void {
    const parsed = typeof value === 'string' ? Number(value) : NaN;

    if (Number.isFinite(parsed) && perPageOptions.includes(parsed)) {
        perPage.value = parsed;
    }
}

/**
 * `from`/`to` are null on an empty result set, so the counter falls back to a
 * plain zero instead of rendering "null–null of 0".
 */
const summary = computed(() => {
    if (meta.total === 0) {
        return `No ${label} found`;
    }

    const from = meta.from ?? 0;
    const to = meta.to ?? 0;
    const noun = meta.total === 1 ? label.replace(/s$/, '') : label;

    return `Showing ${from}–${to} of ${meta.total} ${noun}`;
});

/** A single page of results needs a counter, but not a set of controls. */
const hasPages = computed(() => meta.last_page > 1);
</script>

<template>
    <div
        :class="
            cn(
                'flex flex-col-reverse items-center justify-between gap-4 border-t border-border px-2 py-3 sm:flex-row',
                className,
            )
        "
    >
        <div class="flex items-center gap-4">
            <p
                class="text-sm text-muted-foreground tabular-nums"
                role="status"
                aria-live="polite"
            >
                {{ summary }}
            </p>

            <div
                v-if="perPage !== undefined"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <span :id="perPageLabelId">Rows per page</span>
                <Select
                    :model-value="String(perPage)"
                    @update:model-value="onPerPageChange"
                >
                    <SelectTrigger
                        size="sm"
                        class="w-20 tabular-nums"
                        :aria-labelledby="perPageLabelId"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in perPageOptions"
                            :key="option"
                            :value="String(option)"
                        >
                            {{ option }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <Pagination
            v-if="hasPages"
            v-slot="{ page: currentPage }"
            :page="page"
            :total="meta.total"
            :items-per-page="meta.per_page"
            :sibling-count="siblingCount"
            :disabled="disabled"
            show-edges
            class="mx-0 w-auto justify-end"
            @update:page="page = $event"
        >
            <PaginationContent v-slot="{ items }">
                <PaginationFirst aria-label="Go to first page" />
                <PaginationPrevious aria-label="Go to previous page" />

                <template v-for="(item, index) in items">
                    <PaginationItem
                        v-if="item.type === 'page'"
                        :key="`page-${item.value}`"
                        :value="item.value"
                        :is-active="item.value === currentPage"
                        :aria-label="`Go to page ${item.value}`"
                        :aria-current="
                            item.value === currentPage ? 'page' : undefined
                        "
                        class="tabular-nums"
                    >
                        {{ item.value }}
                    </PaginationItem>

                    <PaginationEllipsis
                        v-else
                        :key="`ellipsis-${index}`"
                        :index="index"
                    />
                </template>

                <PaginationNext aria-label="Go to next page" />
                <PaginationLast aria-label="Go to last page" />
            </PaginationContent>
        </Pagination>
    </div>
</template>
