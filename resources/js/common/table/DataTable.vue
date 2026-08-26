<script setup lang="ts" generic="TRow extends { uuid: string }">
import { ArrowDownIcon, ArrowUpIcon, ChevronsUpDownIcon } from '@lucide/vue';
import { m } from 'motion-v';
import { computed, useSlots } from 'vue';
import EmptyState from '@/common/feedback/EmptyState.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { REVEAL_FADE } from '@/lib/motion';
import { cn } from '@/lib/utils';
import type { DataTableColumn, DataTableSort } from './types';

/**
 * The one table.
 *
 * Transport-agnostic by design: it renders the rows it is handed and reports
 * intent through `v-model:sort` and `v-model:selection`. Sorting and paging are
 * therefore always server-side from the table's point of view — it never
 * reorders `rows` itself, because a component that sorts the current page in
 * place tells the user a comforting lie about the other ninety.
 *
 * ## On motion
 *
 * The surface reveals once, as a whole. There is deliberately no per-row
 * stagger: `lib/motion.ts` already warns that the marketing cascade becomes a
 * tax on operational screens, and a table is the sharpest case of it — 25 rows
 * at even the tight 40ms stagger is a full second before the last row settles,
 * paid again on every page change, by someone scanning for a value whose
 * position they already know. Rows instead cross-fade through the `data-busy`
 * opacity transition, which reads as "this is refreshing" without moving
 * anything the eye is currently tracking.
 */

const {
    rows,
    columns,
    loading = false,
    selectable = false,
    emptyTitle = 'Nothing here yet',
    emptyDescription,
    caption,
    rowClass,
    skeletonRows = 5,
    class: className,
} = defineProps<{
    rows: readonly TRow[];
    columns: readonly DataTableColumn<TRow>[];
    /** Dims the body, or shows skeletons when there is nothing to dim yet. */
    loading?: boolean;
    selectable?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    /** Visually hidden unless a design calls for it; always read by AT. */
    caption?: string;
    /** Per-row classes — soft-deleted rows, overdue invoices, and the like. */
    rowClass?: (row: TRow) => string | undefined;
    skeletonRows?: number;
    class?: string;
}>();

const sort = defineModel<DataTableSort | null>('sort', { default: null });
const selection = defineModel<TRow[]>('selection', { default: () => [] });

defineSlots<
    {
        /** Row actions, pinned right. Wrap each control in a PermissionGuard. */
        actions?: (props: { row: TRow }) => unknown;
        /** Replaces the built-in `EmptyState`. */
        empty?: () => unknown;
    } & {
        /** Custom rendering for one column: `#[cell:status]="{ row }"`. */
        [K in `cell:${string}`]?: (props: {
            row: TRow;
            value: string | number | null | undefined;
        }) => unknown;
    }
>();

const slots = useSlots();

/** Keeps the action column out of the DOM when nobody fills it. */
const hasActions = computed(() => Boolean(slots.actions));

const columnCount = computed(
    () => columns.length + (selectable ? 1 : 0) + (hasActions.value ? 1 : 0),
);

const selectedKeys = computed(
    () => new Set(selection.value.map((row) => row.uuid)),
);

const allSelected = computed(
    () =>
        rows.length > 0 &&
        rows.every((row) => selectedKeys.value.has(row.uuid)),
);

const someSelected = computed(
    () => selection.value.length > 0 && !allSelected.value,
);

/** reka models the mixed state as the literal string, not as a boolean. */
const headerCheckboxState = computed<boolean | 'indeterminate'>(() =>
    someSelected.value ? 'indeterminate' : allSelected.value,
);

const showSkeleton = computed(() => loading && rows.length === 0);
const showEmpty = computed(() => !loading && rows.length === 0);

function toggleAll(checked: boolean | 'indeterminate'): void {
    selection.value = checked === true ? [...rows] : [];
}

function toggleRow(row: TRow, checked: boolean | 'indeterminate'): void {
    selection.value =
        checked === true
            ? [...selection.value, row]
            : selection.value.filter((selected) => selected.uuid !== row.uuid);
}

function toggleSort(column: DataTableColumn<TRow>): void {
    if (!column.sortable) {
        return;
    }

    const current = sort.value;

    sort.value =
        current?.field === column.key
            ? {
                  field: column.key,
                  direction: current.direction === 'asc' ? 'desc' : 'asc',
              }
            : { field: column.key, direction: 'asc' };
}

type AriaSort = 'ascending' | 'descending' | 'none' | undefined;

function ariaSort(column: DataTableColumn<TRow>): AriaSort {
    if (!column.sortable) {
        return undefined;
    }

    if (sort.value?.field !== column.key) {
        return 'none';
    }

    return sort.value.direction === 'asc' ? 'ascending' : 'descending';
}

function alignClass(column: DataTableColumn<TRow>): string {
    switch (column.align) {
        case 'right':
            return 'text-right';
        case 'left':
            return 'text-left';
        default:
            return 'text-center';
    }
}

function cellClass(column: DataTableColumn<TRow>): string {
    return cn(
        alignClass(column),
        column.hideOnMobile && 'hidden md:table-cell',
        column.class,
    );
}

function displayValue(
    column: DataTableColumn<TRow>,
    row: TRow,
): string | number | null | undefined {
    return column.value?.(row);
}
</script>

<template>
    <m.div
        :variants="REVEAL_FADE"
        initial="hidden"
        animate="visible"
        :class="
            cn(
                'w-full overflow-hidden rounded-xl border border-border bg-card',
                className,
            )
        "
    >
        <div class="overflow-x-auto">
            <Table>
                <TableCaption v-if="caption" class="sr-only">
                    {{ caption }}
                </TableCaption>

                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead v-if="selectable" class="w-10 pl-3">
                            <Checkbox
                                :model-value="headerCheckboxState"
                                :disabled="rows.length === 0"
                                aria-label="Select all rows on this page"
                                @update:model-value="toggleAll"
                            />
                        </TableHead>

                        <TableHead
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :class="cellClass(column)"
                            :aria-sort="ariaSort(column)"
                        >
                            <button
                                v-if="column.sortable"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-sm font-medium transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                @click="toggleSort(column)"
                            >
                                {{ column.header }}
                                <ArrowUpIcon
                                    v-if="
                                        sort?.field === column.key &&
                                        sort.direction === 'asc'
                                    "
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <ArrowDownIcon
                                    v-else-if="sort?.field === column.key"
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                <ChevronsUpDownIcon
                                    v-else
                                    class="size-3.5 opacity-50"
                                    aria-hidden="true"
                                />
                            </button>

                            <span v-else>{{ column.header }}</span>
                        </TableHead>

                        <TableHead
                            v-if="hasActions"
                            class="w-px pr-3 text-right"
                        >
                            <span class="sr-only">Actions</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody
                    :data-busy="loading || undefined"
                    class="transition-opacity duration-200 data-[busy]:pointer-events-none data-[busy]:opacity-60"
                >
                    <TableRow
                        v-for="index in showSkeleton ? skeletonRows : 0"
                        :key="`skeleton-${index}`"
                    >
                        <TableCell v-if="selectable" class="pl-3">
                            <Skeleton class="size-4" />
                        </TableCell>
                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="cellClass(column)"
                        >
                            <Skeleton class="mx-auto h-4 w-24" />
                        </TableCell>
                        <TableCell v-if="hasActions" class="pr-3">
                            <Skeleton class="ml-auto h-8 w-20" />
                        </TableCell>
                    </TableRow>

                    <TableRow
                        v-for="row in rows"
                        :key="row.uuid"
                        :data-state="
                            selectedKeys.has(row.uuid) ? 'selected' : undefined
                        "
                        :class="rowClass?.(row)"
                    >
                        <TableCell v-if="selectable" class="pl-3">
                            <Checkbox
                                :model-value="selectedKeys.has(row.uuid)"
                                aria-label="Select row"
                                @update:model-value="toggleRow(row, $event)"
                            />
                        </TableCell>

                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="cellClass(column)"
                        >
                            <slot
                                :name="`cell:${column.key}`"
                                :row="row"
                                :value="displayValue(column, row)"
                            >
                                {{ displayValue(column, row) ?? '—' }}
                            </slot>
                        </TableCell>

                        <TableCell v-if="hasActions" class="pr-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <slot name="actions" :row="row" />
                            </div>
                        </TableCell>
                    </TableRow>

                    <TableRow v-if="showEmpty" class="hover:bg-transparent">
                        <TableCell :colspan="columnCount" class="p-0">
                            <slot name="empty">
                                <EmptyState
                                    :title="emptyTitle"
                                    :description="emptyDescription"
                                />
                            </slot>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </m.div>
</template>
