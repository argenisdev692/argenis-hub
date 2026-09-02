<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { availabilityPresentation } from '../helpers/availabilityPresentation';

/**
 * The `is_available` flag as a badge — "Open" / "Closed" for a date exception,
 * "Available" / "Unavailable" for a weekly rule.
 *
 * The two entities mean the same thing by the column but not the same word, so
 * the wording is a prop rather than two near-identical components.
 */
const { isAvailable, wording = 'open-closed' } = defineProps<{
    isAvailable: boolean;
    /** `open-closed` for exceptions (a day), `available` for rules (a slot). */
    wording?: 'open-closed' | 'available';
}>();

const presentation = computed(() => availabilityPresentation(isAvailable));

const label = computed(() =>
    wording === 'available'
        ? isAvailable
            ? 'Available'
            : 'Unavailable'
        : presentation.value.label,
);
</script>

<template>
    <Badge :variant="presentation.variant">
        <component :is="presentation.icon" class="size-3" aria-hidden="true" />
        {{ label }}
    </Badge>
</template>
