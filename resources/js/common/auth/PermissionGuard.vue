<script setup lang="ts">
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const {
    permission,
    anyOf = [],
    allOf = [],
} = defineProps<{
    /** Shorthand for a single required permission. */
    permission?: string;
    /** Render when the user holds at least one of these. */
    anyOf?: string[];
    /** Render only when the user holds every one of these. */
    allOf?: string[];
}>();

defineSlots<{
    /** Rendered when the check passes. */
    default: () => unknown;
    /** Rendered instead when it fails. Omit to render nothing. */
    denied?: () => unknown;
}>();

const { can, canAny, canAll } = usePermissions();

/**
 * All three props are ANDed, so `permission` can narrow an `anyOf` set without
 * needing a fourth combinator. Passing none renders the slot — a guard with no
 * criteria is a no-op, not a lockout, which keeps a mistyped prop name from
 * silently blanking a screen.
 */
const allowed = computed(
    () =>
        (permission === undefined || can(permission)) &&
        canAny(anyOf) &&
        canAll(allOf),
);
</script>

<template>
    <slot v-if="allowed" />
    <slot v-else name="denied" />
</template>
