<script
    setup
    lang="ts"
    generic="TRow extends { uuid: string; deleted_at: string | null }"
>
import { RotateCcwIcon, Trash2Icon } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

/**
 * Delete / restore for a row selection, with the "is this selection all-active
 * or all-deleted" split that every soft-delete list needs — previously inlined
 * as `selectedActive` / `selectedDeleted` computeds on each page.
 *
 * Authorization stays with the caller: `common/` cannot import the `auth`
 * module's `PermissionGuard`, so the page passes `can-delete` / `can-restore`
 * (and simply omits this component, or the whole toolbar `bulk` slot, when the
 * user may do neither).
 */

const {
    selection,
    canDelete = false,
    canRestore = false,
    busy = false,
} = defineProps<{
    selection: readonly TRow[];
    canDelete?: boolean;
    canRestore?: boolean;
    /** Disables both buttons while a bulk mutation is in flight. */
    busy?: boolean;
}>();

const emit = defineEmits<{
    'bulk-delete': [uuids: string[]];
    'bulk-restore': [uuids: string[]];
}>();

const activeUuids = computed(() =>
    selection.filter((row) => row.deleted_at === null).map((row) => row.uuid),
);

const deletedUuids = computed(() =>
    selection.filter((row) => row.deleted_at !== null).map((row) => row.uuid),
);

/**
 * A mixed selection acts on its matching subset per button (delete → active
 * only, restore → deleted only) instead of disabling both — the disabled
 * button's tooltip always says which rows its action will touch.
 */
const mixedSelection = computed(
    () => activeUuids.value.length > 0 && deletedUuids.value.length > 0,
);

const restoreTitle = computed(() =>
    mixedSelection.value
        ? `Restore the ${deletedUuids.value.length} deleted — active rows are skipped`
        : 'Restore selected rows',
);

const deleteTitle = computed(() =>
    mixedSelection.value
        ? `Delete the ${activeUuids.value.length} active — deleted rows are skipped`
        : 'Delete selected rows',
);

defineSlots<{
    /** Module-specific bulk actions, appended after delete/restore. */
    extra?: (props: { active: string[]; deleted: string[] }) => unknown;
}>();
</script>

<template>
    <div v-if="selection.length > 0" class="flex flex-wrap items-center gap-2">
        <Button
            v-if="canRestore"
            variant="outline"
            size="sm"
            :disabled="busy || deletedUuids.length === 0"
            :title="restoreTitle"
            @click="emit('bulk-restore', deletedUuids)"
        >
            <RotateCcwIcon class="size-4" aria-hidden="true" />
            Restore ({{ deletedUuids.length }})
        </Button>

        <Button
            v-if="canDelete"
            variant="destructive"
            size="sm"
            :disabled="busy || activeUuids.length === 0"
            :title="deleteTitle"
            @click="emit('bulk-delete', activeUuids)"
        >
            <Trash2Icon class="size-4" aria-hidden="true" />
            Delete ({{ activeUuids.length }})
        </Button>

        <slot name="extra" :active="activeUuids" :deleted="deletedUuids" />
    </div>
</template>
