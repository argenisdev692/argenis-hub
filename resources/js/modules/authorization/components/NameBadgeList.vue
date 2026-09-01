<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

/**
 * A capped list of names as badges, with a "+N" overflow chip.
 *
 * A role can hold sixty permissions; rendering all of them turns one table row
 * into a paragraph and destroys the scan line the rest of the column depends
 * on. The overflow chip carries the full list in its `title`, and the detail
 * page shows every one uncapped.
 */
const {
    names,
    max = 3,
    emptyLabel = 'None',
} = defineProps<{
    names: readonly string[];
    /** How many badges to show before collapsing into "+N". */
    max?: number;
    emptyLabel?: string;
}>();

const visible = computed(() => names.slice(0, max));
const overflow = computed(() => names.slice(max));
</script>

<template>
    <span v-if="names.length === 0" class="text-sm text-muted-foreground">{{
        emptyLabel
    }}</span>

    <div v-else class="flex flex-wrap items-center justify-center gap-1">
        <Badge v-for="name in visible" :key="name" variant="secondary">
            {{ name }}
        </Badge>

        <Badge
            v-if="overflow.length > 0"
            variant="outline"
            :title="overflow.join(', ')"
        >
            +{{ overflow.length }}
        </Badge>
    </div>
</template>
