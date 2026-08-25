<script setup lang="ts">
import { GripVerticalIcon } from '@lucide/vue';
import { VueDraggable } from 'vue-draggable-plus';
import { cn } from '@/lib/utils';

/**
 * Any row shape works; `id` is only used for the `:key`, so pass whatever your
 * records already carry.
 */
type SortableItem = { id: string | number } & Record<string, unknown>;

const {
    disabled = false,
    handle = true,
    emptyText = 'Nothing to sort yet.',
    class: className,
} = defineProps<{
    disabled?: boolean;
    /** Drag by the grip only. Off = the whole row is draggable. */
    handle?: boolean;
    emptyText?: string;
    class?: string;
}>();

const model = defineModel<SortableItem[]>({ default: () => [] });

defineSlots<{
    default: (props: { item: SortableItem; index: number }) => unknown;
    empty?: () => unknown;
}>();
</script>

<template>
    <div :class="cn('w-full', className)">
        <VueDraggable
            v-model="model"
            :disabled="disabled"
            :handle="handle ? '[data-drag-handle]' : undefined"
            :animation="150"
            ghost-class="opacity-40"
            drag-class="shadow-lg"
            class="flex flex-col gap-2"
            role="list"
        >
            <div
                v-for="(item, index) in model"
                :key="item.id"
                role="listitem"
                :class="
                    cn(
                        'flex items-center gap-3 rounded-md border border-input bg-card px-3 py-2',
                        disabled && 'opacity-50',
                    )
                "
            >
                <button
                    v-if="handle"
                    type="button"
                    data-drag-handle
                    :disabled="disabled"
                    aria-label="Reorder item"
                    class="cursor-grab rounded-xs text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none active:cursor-grabbing disabled:cursor-not-allowed"
                >
                    <GripVerticalIcon class="size-4" />
                </button>

                <div class="min-w-0 flex-1">
                    <slot :item="item" :index="index" />
                </div>
            </div>
        </VueDraggable>

        <p
            v-if="!model.length"
            class="py-6 text-center text-sm text-muted-foreground"
        >
            <slot name="empty">{{ emptyText }}</slot>
        </p>
    </div>
</template>
