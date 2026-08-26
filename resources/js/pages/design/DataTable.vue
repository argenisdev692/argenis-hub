<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { RotateCcwIcon, Trash2Icon } from '@lucide/vue';
import { computed, onWatcherCleanup, ref, watch } from 'vue';
import ThemeToggle from '@/common/feedback/ThemeToggle.vue';
import type {
    DataTableColumn,
    DataTableSort,
    PaginationMeta,
} from '@/common/table';
import { ConfirmModal, DataTable, Paginator } from '@/common/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

/**
 * Development reference for the table kit.
 *
 * The kit is transport-agnostic, so this page plays the part the server
 * normally plays: it filters, sorts and slices the fixture itself and hands
 * `DataTable` one page of rows plus a `PaginationMeta`. Swapping the three
 * computeds below for a Pinia Colada `useQuery` is the whole difference between
 * this and a real screen.
 */

type DemoRow = {
    uuid: string;
    name: string;
    email: string;
    status: 'active' | 'invited' | 'suspended';
    created_at: string;
    deleted_at: string | null;
};

const FIRST = [
    'Ada',
    'Grace',
    'Alan',
    'Edsger',
    'Barbara',
    'Linus',
    'Katherine',
];
const LAST = [
    'Lovelace',
    'Hopper',
    'Turing',
    'Dijkstra',
    'Liskov',
    'Torvalds',
    'Johnson',
];
const STATUSES: DemoRow['status'][] = ['active', 'invited', 'suspended'];

/** Deterministic so the page looks the same on every reload. */
const rows = ref<DemoRow[]>(
    Array.from({ length: 43 }, (_, index) => {
        const first = FIRST[index % FIRST.length];
        const last = LAST[(index * 3) % LAST.length];

        return {
            uuid: `demo-${String(index).padStart(3, '0')}`,
            name: `${first} ${last}`,
            email: `${first.toLowerCase()}.${last.toLowerCase()}@example.com`,
            status: STATUSES[index % STATUSES.length],
            created_at: new Date(2026, index % 12, (index % 27) + 1)
                .toISOString()
                .slice(0, 10),
            deleted_at: index % 11 === 0 ? '2026-08-01' : null,
        };
    }),
);

const columns: DataTableColumn<DemoRow>[] = [
    {
        key: 'name',
        header: 'Name',
        value: (row) => row.name,
        sortable: true,
        align: 'left',
    },
    {
        key: 'email',
        header: 'Email',
        value: (row) => row.email,
        align: 'left',
        hideOnMobile: true,
    },
    { key: 'status', header: 'Status' },
    {
        key: 'created_at',
        header: 'Created',
        value: (row) => row.created_at,
        sortable: true,
        hideOnMobile: true,
    },
];

const search = ref('');
const debouncedSearch = ref('');
const page = ref(1);
const perPage = 10;
const sort = ref<DataTableSort | null>({ field: 'name', direction: 'asc' });
const selection = ref<DemoRow[]>([]);
const loading = ref(false);
const confirmDeleteOpen = ref(false);

/**
 * Debounced search, with the timer torn down by `onWatcherCleanup`.
 *
 * Without the cleanup a fast typist leaves one pending timeout per keystroke,
 * and the last few all fire after the final one has already been applied —
 * which is how a list ends up showing results for a prefix of what is in the box.
 */
watch(search, (value) => {
    loading.value = true;

    const timer = setTimeout(() => {
        debouncedSearch.value = value;
        page.value = 1;
        loading.value = false;
    }, 300);

    onWatcherCleanup(() => clearTimeout(timer));
});

const filtered = computed(() => {
    const needle = debouncedSearch.value.trim().toLowerCase();

    if (!needle) {
        return rows.value;
    }

    return rows.value.filter(
        (row) =>
            row.name.toLowerCase().includes(needle) ||
            row.email.toLowerCase().includes(needle),
    );
});

const sorted = computed(() => {
    const active = sort.value;

    if (!active) {
        return filtered.value;
    }

    const direction = active.direction === 'asc' ? 1 : -1;

    return [...filtered.value].sort((a, b) => {
        const left = active.field === 'created_at' ? a.created_at : a.name;
        const right = active.field === 'created_at' ? b.created_at : b.name;

        return left.localeCompare(right) * direction;
    });
});

const meta = computed<PaginationMeta>(() => {
    const total = sorted.value.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const current = Math.min(page.value, lastPage);
    const from = total === 0 ? null : (current - 1) * perPage + 1;
    const to = total === 0 ? null : Math.min(current * perPage, total);

    return {
        current_page: current,
        last_page: lastPage,
        per_page: perPage,
        from,
        to,
        total,
    };
});

const pageRows = computed(() =>
    sorted.value.slice(
        (meta.value.current_page - 1) * perPage,
        meta.value.current_page * perPage,
    ),
);

const selectedActive = computed(() =>
    selection.value.filter((row) => row.deleted_at === null),
);

const selectedDeleted = computed(() =>
    selection.value.filter((row) => row.deleted_at !== null),
);

/** Soft delete, so the deleted-row styling has something to style. */
function deleteSelected(): void {
    const doomed = new Set(selectedActive.value.map((row) => row.uuid));

    rows.value = rows.value.map((row) =>
        doomed.has(row.uuid) ? { ...row, deleted_at: '2026-08-26' } : row,
    );

    selection.value = [];
    confirmDeleteOpen.value = false;
}

function restoreSelected(): void {
    const revived = new Set(selectedDeleted.value.map((row) => row.uuid));

    rows.value = rows.value.map((row) =>
        revived.has(row.uuid) ? { ...row, deleted_at: null } : row,
    );

    selection.value = [];
}

function rowClass(row: DemoRow): string | undefined {
    return row.deleted_at ? 'bg-muted/40 opacity-60' : undefined;
}

function statusVariant(
    status: DemoRow['status'],
): 'default' | 'secondary' | 'destructive' {
    switch (status) {
        case 'active':
            return 'default';
        case 'suspended':
            return 'destructive';
        default:
            return 'secondary';
    }
}
</script>

<template>
    <Head title="Table kit" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Table kit</h1>
                <p class="text-sm text-muted-foreground">
                    <code>DataTable</code> · <code>Paginator</code> ·
                    <code>ConfirmModal</code> — server-shaped, fixture-driven
                </p>
            </div>
            <ThemeToggle />
        </header>

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                class="max-w-xs"
                type="search"
                placeholder="Search name or email…"
                aria-label="Search rows"
            />

            <div class="ml-auto flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="selectedDeleted.length === 0"
                    @click="restoreSelected"
                >
                    <RotateCcwIcon class="size-4" aria-hidden="true" />
                    Restore ({{ selectedDeleted.length }})
                </Button>

                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="selectedActive.length === 0"
                    @click="confirmDeleteOpen = true"
                >
                    <Trash2Icon class="size-4" aria-hidden="true" />
                    Delete ({{ selectedActive.length }})
                </Button>
            </div>
        </div>

        <div class="flex flex-col">
            <DataTable
                v-model:sort="sort"
                v-model:selection="selection"
                :rows="pageRows"
                :columns="columns"
                :loading="loading"
                :row-class="rowClass"
                selectable
                caption="Demonstration rows for the table kit"
                empty-title="No matching rows"
                empty-description="Try a different search term."
                class="rounded-b-none border-b-0"
            >
                <template #[`cell:status`]="{ row }">
                    <Badge :variant="statusVariant(row.status)">
                        {{ row.status }}
                    </Badge>
                </template>

                <template #actions="{ row }">
                    <span class="text-xs text-muted-foreground">
                        {{ row.deleted_at ? 'deleted' : 'active' }}
                    </span>
                </template>
            </DataTable>

            <Paginator
                v-model:page="page"
                :meta="meta"
                :disabled="loading"
                label="people"
                class="rounded-b-xl border border-border bg-card"
            />
        </div>
    </div>

    <ConfirmModal
        v-model:open="confirmDeleteOpen"
        destructive
        title="Delete the selected rows?"
        :description="`${selectedActive.length} row(s) will be soft-deleted. You can restore them afterwards.`"
        confirm-label="Delete"
        @confirm="deleteSelected"
    />
</template>
