<script setup lang="ts">
import { computed } from 'vue';

const { status, deletedAt = null } = defineProps<{
    status: string;
    deletedAt?: string | null;
}>();

const label = computed(() => {
    if (deletedAt !== null) {
        return 'Suspended';
    }

    switch (status) {
        case 'new':
            return 'New';
        case 'saved':
            return 'Saved';
        case 'applied':
            return 'Applied';
        case 'dismissed':
            return 'Dismissed';
        case 'skipped':
            return 'Skipped';
        case 'reference':
            return 'Open manually';
        default:
            return status;
    }
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
    >
        {{ label }}
    </span>
</template>
