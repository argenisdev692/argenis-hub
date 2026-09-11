<script setup lang="ts">
import { AlertTriangleIcon, CheckIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

/**
 * The settlement axis: paid, outstanding, or overdue.
 *
 * "Overdue" is not a stored column — it is `!is_paid && due_date < today`,
 * derived here rather than on the server so a tab left open overnight still
 * turns the badge red in the morning without a refetch.
 */
const { isPaid, overdue = false } = defineProps<{
    isPaid: boolean;
    overdue?: boolean;
}>();

const tone = computed(() => {
    if (isPaid) {
        return { variant: 'default' as const, label: 'Paid' };
    }

    return overdue
        ? { variant: 'destructive' as const, label: 'Overdue' }
        : { variant: 'outline' as const, label: 'Unpaid' };
});
</script>

<template>
    <Badge :variant="tone.variant">
        <CheckIcon v-if="isPaid" aria-hidden="true" />
        <AlertTriangleIcon v-else-if="overdue" aria-hidden="true" />
        {{ tone.label }}
    </Badge>
</template>
